# Red del Checador — guía rápida para cambios de red

Parte de la memoria del proyecto (enlazado desde `CLAUDE.md`, UPD-704). Actualizar aquí cada vez que cambie la red.

## Estado actual (15-oct-2026)

| Equipo | IP | Conexión | Notas |
|---|---|---|---|
| Reloj ZKTeco MB20-VL (serie SPK7251000343) | **192.168.15.30** fija | Cable (al extender Tenda) | Manda checadas por ADMS a la Pi |
| Raspberry Pi 4 | **192.168.15.75** (fijar si no quedó) | WiFi `Produccion_Apex` (`wlan0`); `eth0` sin cable | Usuario SSH `areyna1976`. Tiene Tailscale |
| Listener en la Pi | puerto **8081** | servicio `checador-listener` | Recibe del reloj y reenvía a `https://apex.glass/produccion/api/checador.php` |
| Router | 192.168.15.1 (verificar) | — | DHCP reparte desde .100 → usar IPs fijas < .100 |
| Equipo desconocido | 192.168.15.77 | — | Responde ping, sin identificar |

Cadena: **Reloj (.30) → Pi (.75:8081) → internet → apex.glass**. Todo lo inicia la Pi (Apex nunca se conecta a la Pi).
Si algo falla, el reloj guarda las checadas y las reenvía al reconectarse (pasó el 14-oct: entraron 204 atrasadas).

## ANTES de mover la red

1. Anota la IP de Tailscale de la Pi (para entrar aunque cambie la IP local): en la Pi `tailscale ip -4`.
   Desde otra máquina con Tailscale: `ssh areyna1976@<IP-tailscale>`.
2. Si cambia el nombre/clave del WiFi: agrega la red nueva en la Pi **antes** de apagar la vieja (ver abajo), o ten a la mano monitor + teclado.
3. Confirma que hoy todo llega (sección "Verificar").

## Comandos en la Pi

```bash
# Estado de red
nmcli device status
nmcli device wifi list | head -5          # señal (>60 bien, <40 se corta)
hostname -I
ip route | grep default                   # puerta de enlace

# Configuración de IP de la conexión WiFi
nmcli -f ipv4.method,ipv4.addresses,ipv4.gateway,ipv4.dns con show "netplan-wlan0-Produccion_Apex"

# Fijar IP (ajustar NOMBRE / gateway si cambian)
sudo nmcli con mod "netplan-wlan0-Produccion_Apex" ipv4.method manual \
  ipv4.addresses 192.168.15.75/24 ipv4.gateway 192.168.15.1 ipv4.dns "192.168.15.1 8.8.8.8"
sudo nmcli con up "netplan-wlan0-Produccion_Apex"

# Agregar una red WiFi nueva (sin quitar la actual)
sudo nmcli device wifi connect "NOMBRE_RED" password "CLAVE"

# Pasar a cable: conectar el cable y fijar la IP en la conexión cableada
nmcli con show                            # ver el nombre de la conexión de eth0

# Listener del checador
sudo ss -tlnp | grep 8081                 # debe aparecer python3
sudo systemctl status checador-listener
sudo systemctl restart checador-listener
tail -30 ~/checador_listener.log
ping -c 2 192.168.15.30                   # la Pi ve al reloj
```

Ojo: la conexión la maneja netplan; si un cambio con `nmcli` no sobrevive un reinicio, revisar `/etc/netplan/*.yaml`.

## En el reloj

- IP propia: *Menú → Comunicación → Ethernet* (hoy .30 fija, gateway .1).
- Destino: *Menú → Comunicación → Servidor en la nube (ADMS)* → **IP de la Pi**, puerto **8081**. Si la IP de la Pi cambia, cambiarla aquí. A veces requiere reiniciar el reloj.

## Verificar (desde la PC o pedírselo a Claude en el VPS)

- PC en la misma red: `ping 192.168.15.75` y `ping 192.168.15.30`.
- En el VPS (Claude), última checada recibida y último contacto de la Pi:
  ```bash
  mysql -e "SELECT MAX(fecha_hora), MAX(created_at) FROM checador_checadas" apexglass2025_prod
  grep Checador-Listener /var/log/httpd/users/apexglass2025/apex.glass/access_log | tail -3
  ```
  La Pi consulta `accion=pendientes` cada ~15 s; si no aparece, la Pi no tiene internet o el servicio está caído.
  Si aparece `pendientes` pero no `registrar_checadas` tras una checada de prueba → el reloj no llega a la Pi (revisar ADMS).

## Fallas típicas

| Síntoma | Causa probable | Arreglo |
|---|---|---|
| `ssh` "timed out" y ping "Destination host unreachable" | PC en otra red/WiFi | Conectar la PC a `Produccion_Apex` |
| Pi consulta Apex pero no llegan checadas | Reloj apunta a IP vieja de la Pi | Cambiar ADMS en el reloj |
| Pi deja de consultar Apex | Sin WiFi/internet o servicio caído | `nmcli device status`, `systemctl restart checador-listener` |
| IP de la Pi cambió sola | IP por DHCP | Fijarla (comando arriba) o reservarla en el router |

## Historial
- 07-sep-2026 (UPD-567): Pi en .171 (DHCP), reloj .30.
- 06/07-oct-2026: la Pi deja de comunicarse; 07-oct sin ninguna checada.
- 14-oct-2026 (UPD-704): Pi reaparece en .75; reloj repuntado a .75:8081; entran 204 checadas del 08 al 14-oct.
