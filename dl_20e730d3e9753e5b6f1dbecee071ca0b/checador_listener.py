#!/usr/bin/env python3
"""
Checador ZKTeco — listener de producción (Fase 2, UPD-570/574)
================================================================
Corre en la Raspberry Pi. Reemplaza al adms_listener.py de prueba (UPD-567,
que solo imprimía lo que recibía).

Hace DOS cosas en paralelo:

1. Sirve el protocolo ADMS/PUSH del reloj MB20-VL en el puerto 8081
   (el reloj ya apunta aquí, no hay que tocar nada en el dispositivo):
     - GET  /iclock/cdata   (handshake + push de ATTLOG/OPERLOG)
     - GET  /iclock/getrequest  (el reloj pregunta "¿tienes algo para mí?")
     - POST /iclock/devicecmd   (el reloj reporta si un comando se aplicó)

2. Cada POLL_SEGUNDOS hace polling saliente a Apex
   (GET .../api/checador.php?accion=pendientes) para traer comandos de
   alta/baja encolados desde la UI de RH, y cuando el reloj confirma uno,
   avisa de vuelta a Apex (POST .../api/checador.php?accion=confirmar).

IMPORTANTE — arquitectura a propósito: todo el tráfico Pi↔VPS lo inicia
SIEMPRE la Pi (poll saliente), nunca al revés. No se necesita abrir ningún
puerto entrante en el VPS ni túnel hacia él. Ver CLAUDE.md sección 12
(Checador ZKTeco) para el porqué.

ADVERTENCIA HONESTA: la parte de cdata/getrequest ya está probada en vivo
con este reloj exacto (UPD-567) para el handshake y la recepción de datos.
La parte de ENVIAR comandos (getrequest con USERINFO / devicecmd) usa la
sintaxis estándar del protocolo ADMS/PUSH de ZKTeco, pero NO se ha probado
todavía contra este firmware exacto (ZMM510_TFT). Es razonable que la
primera prueba real tenga que ajustarse — revisa checador_listener.log
línea por línea en la primera prueba y avísale a Claude si algo no
coincide con lo esperado.

Sin dependencias externas — solo librería estándar de Python 3.
"""
import http.server
import socketserver
import threading
import time
import json
import logging
import os
import urllib.request
import urllib.error
import urllib.parse
from datetime import datetime

# ── Configuración ────────────────────────────────────────────────────────────
ADMS_HOST = '0.0.0.0'
ADMS_PORT = 8081

APEX_CHECADOR_URL = 'https://apex.glass/produccion/api/checador.php'
# Misma llave que api/config.php define como CHECADOR_LISTENER_KEY (.env del VPS).
# Se puede sobreescribir con la variable de entorno CHECADOR_LISTENER_KEY sin
# tocar este archivo (recomendado si compartes este script).
CHECADOR_LISTENER_KEY = os.environ.get(
    'CHECADOR_LISTENER_KEY',
    '28d6eee9bfc3609de5c51f330caec21d5250c4cdf6f52243fe39117f863614d9'
)

POLL_SEGUNDOS = 15  # cada cuánto pregunta a Apex por comandos nuevos

LOG_FILE = os.path.expanduser('~/checador_listener.log')

# ── Logging ──────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler()
    ]
)
log = logging.getLogger('checador')

# ── Estado compartido entre el hilo de polling y el servidor ADMS ────────────
_lock = threading.Lock()
_comandos_por_entregar = []   # [{id, tipo, pin, nombre_reloj}, ...] — llegan de Apex, esperan al próximo getrequest
_comandos_entregados = {}     # id_comando -> dict, ya se mandaron al reloj, esperando devicecmd


def apex_get(accion):
    url = APEX_CHECADOR_URL + '?accion=' + accion
    req = urllib.request.Request(url, headers={'X-Checador-Key': CHECADOR_LISTENER_KEY})
    with urllib.request.urlopen(req, timeout=15) as resp:
        return json.loads(resp.read().decode('utf-8'))


def apex_post(accion, payload):
    url = APEX_CHECADOR_URL + '?accion=' + accion
    data = json.dumps(payload).encode('utf-8')
    req = urllib.request.Request(url, data=data, method='POST', headers={
        'X-Checador-Key': CHECADOR_LISTENER_KEY,
        'Content-Type': 'application/json'
    })
    with urllib.request.urlopen(req, timeout=15) as resp:
        return json.loads(resp.read().decode('utf-8'))


def hilo_polling_apex():
    """Cada POLL_SEGUNDOS trae comandos pendientes de Apex y los deja listos
    para el próximo getrequest del reloj."""
    log.info('Hilo de polling a Apex arrancado (cada %ss) -> %s', POLL_SEGUNDOS, APEX_CHECADOR_URL)
    while True:
        try:
            data = apex_get('pendientes')
            comandos = data.get('comandos', [])
            if comandos:
                with _lock:
                    _comandos_por_entregar.extend(comandos)
                log.info('Apex entregó %d comando(s) nuevo(s): %s',
                          len(comandos), [(c['id'], c['tipo'], c['pin']) for c in comandos])
        except urllib.error.HTTPError as e:
            log.error('Error HTTP consultando pendientes en Apex: %s %s', e.code, e.reason)
        except Exception as e:
            log.error('Error de red consultando pendientes en Apex: %s', e)
        time.sleep(POLL_SEGUNDOS)


def confirmar_a_apex(comando_id, ok, mensaje=''):
    try:
        apex_post('confirmar', {'id': comando_id, 'ok': bool(ok), 'mensaje': mensaje})
        log.info('Confirmado a Apex: comando #%s ok=%s (%s)', comando_id, ok, mensaje)
    except Exception as e:
        log.error('No se pudo confirmar a Apex el comando #%s: %s — reintentará "reintentar" manual desde RH si hace falta', comando_id, e)


def construir_lineas_comando():
    """Arma las líneas C:<id>:DATA ... que espera el reloj, tomando lo que
    haya en _comandos_por_entregar, y las mueve a _comandos_entregados
    (esperando la confirmación por /iclock/devicecmd)."""
    lineas = []
    with _lock:
        pendientes = list(_comandos_por_entregar)
        _comandos_por_entregar.clear()
        for c in pendientes:
            cid = c['id']
            if c['tipo'] == 'alta':
                nombre = (c.get('nombre_reloj') or '').replace('\t', ' ')[:24]
                linea = 'C:%d:DATA UPDATE USERINFO PIN=%s\tName=%s\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0' % (
                    cid, c['pin'], nombre)
            elif c['tipo'] == 'baja':
                linea = 'C:%d:DATA DELETE USERINFO PIN=%s' % (cid, c['pin'])
            else:
                log.warning('Tipo de comando desconocido, se ignora: %s', c)
                continue
            lineas.append(linea)
            _comandos_entregados[cid] = c
            log.info('Entregando al reloj: %s', linea)
    return lineas


# ── Servidor ADMS ─────────────────────────────────────────────────────────────
class ADMSHandler(http.server.BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        log.info('%s - %s', self.address_string(), fmt % args)

    def _responder(self, texto, code=200):
        body = texto.encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', 'text/plain; charset=UTF-8')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _leer_body(self):
        length = int(self.headers.get('Content-Length', 0) or 0)
        if length <= 0:
            return b''
        return self.rfile.read(length)

    def do_GET(self):
        parsed = urllib.parse.urlparse(self.path)
        qs = urllib.parse.parse_qs(parsed.query)

        if parsed.path == '/iclock/cdata':
            self._handle_cdata_get(qs)
        elif parsed.path == '/iclock/getrequest':
            self._handle_getrequest(qs)
        else:
            log.info('GET no reconocido: %s', self.path)
            self._responder('OK')

    def do_POST(self):
        parsed = urllib.parse.urlparse(self.path)
        qs = urllib.parse.parse_qs(parsed.query)
        body = self._leer_body()

        if parsed.path == '/iclock/cdata':
            self._handle_cdata_post(qs, body)
        elif parsed.path == '/iclock/devicecmd':
            self._handle_devicecmd(qs, body)
        else:
            log.info('POST no reconocido: %s (body=%r)', self.path, body[:200])
            self._responder('OK')

    # -- /iclock/cdata (GET = handshake inicial, POST = push de datos) -------
    def _handle_cdata_get(self, qs):
        sn = qs.get('SN', ['?'])[0]
        if 'options' in qs:
            log.info('Handshake ADMS de %s', sn)
            respuesta = (
                'GET OPTION FROM: %s\r\n'
                'Stamp=9999\r\n'
                'OpStamp=9999\r\n'
                'ErrorDelay=30\r\n'
                'Delay=10\r\n'
                'TransFlag=1111111111\r\n'
                'TransInterval=1\r\n'
                'TransTables=ALL\r\n'
                'Realtime=1\r\n'
                'Encrypt=None\r\n'
            ) % sn
            self._responder(respuesta)
        else:
            self._responder('OK')

    def _handle_cdata_post(self, qs, body):
        sn = qs.get('SN', ['?'])[0]
        tabla = qs.get('table', ['?'])[0]
        texto = body.decode('utf-8', errors='replace')
        lineas = [l for l in texto.split('\n') if l.strip()]
        log.info('Push de %s, tabla=%s, %d línea(s)', sn, tabla, len(lineas))
        for l in lineas:
            log.info('  %s: %s', tabla, l.strip())
        # Nota: hoy solo se registra en el log (ATTLOG/OPERLOG). El reporte
        # semanal de asistencia hacia Apex (jueves-miércoles, ver CLAUDE.md
        # sección 1) es un proyecto aparte, todavía no construido.
        self._responder('OK: %d' % len(lineas))

    # -- /iclock/getrequest (el reloj pregunta si hay comandos) --------------
    def _handle_getrequest(self, qs):
        sn = qs.get('SN', ['?'])[0]
        lineas = construir_lineas_comando()
        if lineas:
            respuesta = '\n'.join(lineas) + '\n'
            log.info('Respondiendo a getrequest de %s con %d comando(s)', sn, len(lineas))
        else:
            respuesta = 'OK'
        self._responder(respuesta)

    # -- /iclock/devicecmd (el reloj confirma si un comando se aplicó) -------
    def _handle_devicecmd(self, qs, body):
        texto = body.decode('utf-8', errors='replace')
        log.info('devicecmd body: %r', texto)
        entradas = [l for l in texto.replace('\r', '\n').split('\n') if l.strip()]
        if not entradas:
            entradas = [texto]

        for entrada in entradas:
            campos = urllib.parse.parse_qs(entrada)
            cid_raw = campos.get('ID', [None])[0]
            ret_raw = campos.get('Return', [None])[0]
            if cid_raw is None:
                continue
            try:
                cid = int(cid_raw)
            except ValueError:
                log.warning('ID de comando no numérico en devicecmd: %r', cid_raw)
                continue

            with _lock:
                comando = _comandos_entregados.pop(cid, None)

            ok = (ret_raw == '0')
            mensaje = 'Return=%s' % ret_raw
            if comando is None:
                log.warning('El reloj confirmó el comando #%s pero no estaba en la lista de entregados (¿se reinició el listener?) — se reporta a Apex de todas formas', cid)
            confirmar_a_apex(cid, ok, mensaje)

        self._responder('OK')


def main():
    log.info('=' * 70)
    log.info('Checador listener de producción arrancando en %s:%d', ADMS_HOST, ADMS_PORT)
    log.info('Apex: %s', APEX_CHECADOR_URL)
    log.info('Log: %s', LOG_FILE)
    log.info('=' * 70)

    hilo = threading.Thread(target=hilo_polling_apex, daemon=True)
    hilo.start()

    servidor = socketserver.ThreadingTCPServer((ADMS_HOST, ADMS_PORT), ADMSHandler)
    servidor.daemon_threads = True
    try:
        servidor.serve_forever()
    except KeyboardInterrupt:
        log.info('Detenido por teclado')
        servidor.shutdown()


if __name__ == '__main__':
    main()
