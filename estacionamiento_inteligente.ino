#include <WiFi.h>
#include <HTTPClient.h>

#define TRIG_PIN    27
#define ECHO_PIN    13
#define BUZZER_PIN  14

// WiFi Credentials
const char* ssid = "SSID";
const char* password = "SSID PASSWORD";

// Server URL - UPDATED to match your new network IP
const char* serverUrl = "http://10.83.211.98:8000/api.php";

// Variables
long duration;
float distance;
String estado;
int frecuenciaBuzzer = 0;
unsigned long lastTime = 0;
unsigned long timerDelay = 2000; // Send data every 2 seconds

void setup() {
  Serial.begin(115200);
  
  // Configuration of pins
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  
  // Ensure trigger starts LOW
  digitalWrite(TRIG_PIN, LOW);
  
  Serial.println("Sistema de Estacionamiento Inteligente Iniciado");
  Serial.println("----------------------------------------------");
  
  // Connect to WiFi
  Serial.print("Conectando a WiFi: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);
  
  // Wait for connection
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 15) {
    delay(1000);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Conectado con éxito!");
    Serial.print("Dirección IP del ESP32: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nNo se pudo conectar al WiFi. El sistema continuará en modo local (Serial).");
  }
}

void loop() {
  // 1. Send trigger pulse
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  
  // 2. Read echo pulse duration
  duration = pulseIn(ECHO_PIN, HIGH);
  
  // 3. Calculate distance in cm (speed of sound / 2 for round trip)
  distance = duration * 0.034 / 2;
  
  // 4. Evaluate state and control buzzer
  if (distance > 20.0) {
    estado = "Libre";
    frecuenciaBuzzer = 0;
    noTone(BUZZER_PIN);  // Turn off buzzer
  }
  else if (distance >= 10.0 && distance <= 20.0) {
    estado = "Acercandose";
    frecuenciaBuzzer = 1000;
    tone(BUZZER_PIN, frecuenciaBuzzer);  // Sound 1000 Hz
  }
  else if (distance < 10.0) {
    estado = "Ocupado";
    frecuenciaBuzzer = 2000;
    tone(BUZZER_PIN, frecuenciaBuzzer);  // Sound 2000 Hz
  }
  
  // 5. Show data in serial monitor
  Serial.print("Distancia: ");
  Serial.print(distance);
  Serial.print(" cm  |  Estado: ");
  Serial.print(estado);
  Serial.print("  |  Frecuencia Buzzer: ");
  if (frecuenciaBuzzer == 0) {
    Serial.println("Inactiva");
  } else {
    Serial.print(frecuenciaBuzzer);
    Serial.println(" Hz");
  }
  
  // 6. Send data to web server via WiFi (every 'timerDelay' milliseconds)
  if ((millis() - lastTime) > timerDelay) {
    if (WiFi.status() == WL_CONNECTED) {
      WiFiClient client;
      HTTPClient http;
      
      // Initialize HTTP connection using the WiFiClient
      http.begin(client, serverUrl);
      
      // Specify content-type header
      http.addHeader("Content-Type", "application/json");
      
      // Create JSON payload manually
      String jsonPayload = "{\"distance\":" + String(distance, 2) + 
                            ",\"estado\":\"" + estado + 
                            "\",\"frecuencia_buzzer\":" + String(frecuenciaBuzzer) + "}";
      
      // Send POST request
      int httpResponseCode = http.POST(jsonPayload);
      
      if (httpResponseCode > 0) {
        String response = http.getString();
        Serial.print("HTTP Response code: ");
        Serial.println(httpResponseCode);
        Serial.print("Response: ");
        Serial.println(response);
      } else {
        Serial.print("Error sending POST request: ");
        Serial.println(http.errorToString(httpResponseCode).c_str());
      }
      
      // Free resources
      http.end();
    } else {
      Serial.println("WiFi desconectado. No se puede enviar datos via HTTP.");
    }
    lastTime = millis();
  }
  
  // Small pause between loops
  delay(500);
}
