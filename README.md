# Sistema embebido de Estacionamiento Inteligente
## Objetivo
Generar un aplicación de un sistema embebido que detecte la cercanía de un automóvil con un sensor con buzzer y envíe datos a una base de datos para construir un dashboard de monitoreo.

### Lógica del sistema: 
El sistema embebido evaluará los siguientes estados:
  Libre: La distancia deberá de ser mayor que 20 cm
  Acercándose: La distancia deberá de ser entre 10 y 20 cm y como actuador el buzzer emitirá un sonido con la frecuencia 1000Hz.
  Ocupado/Alto: La distancia deberá ser menor que 10 cm y como actuador el buzzer emitirá un sonido conla frecuencia 2000Hz.

## Actividades a realizar
### Paso 1: Simulación
  Armar y simular el circuito en Wokwi.
  Programar la lectura de distancia con el sensor ultrasónico.
  Activar el buzzer según la cercanía.
  Mostrar el estado: Libre, Acercándose u Ocupado.

### Paso 2: Sistema embebido físico:
  Armar el circuito físico.
  Programar en Arduino IDE el código necesario para que el sensor tome la lectura correspondiente, active el actuador en caso de ser necesario y muestre el estado en el monitor serial.

### Paso 3: Sistema IoT
  Genera una base de datos en donde se puedan enviar los datos a través del protocolo WiFi.
  Genera el programa en Arduino IDE para que el sistema embebido mande la información correspondiente.
  Genera el programa en PHP necesario para conectar con el código en Arduino para conectar con la base de datos.
  Obtener con su sistema embebido datos verídicos (Aproximadamente 150 datos).
  Generar una vista que les permita tener una consulta de los datos en donde muestre los valores tomados, el estado y alerta.

### Paso 4: Dashboard
Elaborar un dashboard que contenga:
  Título: Dashboard Estacionamiento Inteligente
  Cards: Distancia actual, Estado del Cajón, Alerta Sonora (Inactiva/Activda), Total de Lecturas, Total espacios ocupados.
  Gráficas: Historial de distancia (Mostrar la distancia por horas), Estados de Cajón (Porcentaje los estados del cajón), Frecuencia de las alertas sonoras, Porcentaje de Ocupación de los cajones de estacionamiento.
  Listado de las últimas 5 lecturas.
