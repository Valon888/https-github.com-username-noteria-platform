# Shembuj komunikimi AI ↔️ dron (UART, detektim, WiFi)
# ===============================
#   SIMULIMI PO FUNKSIONON!      
# ===============================
print("\n===============================")
print("   SIMULIMI PO FUNKSIONON!   ")
print("===============================\n")

# 1. Python UART Serial komunikim me ESP32/Arduino
import time
# >>> TEST PA HARDWARE <<<
# ser = serial.Serial('COM5', 115200, timeout=1)
# time.sleep(2)
# ser.write(b'T')
print("[TEST] Komanda 'T' u dërgua te droni! (simulim)")
# ser.close()

# 2. Python detektim objektesh dhe aktivizim droni
## import torch
## import serial
# >>> TEST PA HARDWARE <<<
# model = torch.hub.load('ultralytics/yolov5', 'custom', path='model.pt')
# ser = serial.Serial('COM5', 115200, timeout=1)
# img = 'frame.jpg'
# results = model(img)
# for det in results.xyxy[0]:
#     label = results.names[int(det[5])]
#     if label in ['drogë', 'armë']:
#         ser.write(b'T')
#         print("Alarm: Objekt i dyshimtë u detektua, komanda dërguar!")
# ser.close()
print("[TEST] Detektimi i objektit dhe komanda dërguar! (simulim)")

# 3. Python komunikim me ESP32/Arduino përmes WiFi (socket)
import socket

ESP32_IP = '192.168.1.50'
PORT = 1234
s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
s.connect((ESP32_IP, PORT))
s.send(b'T')
print("Komanda 'T' u dërgua përmes WiFi! (simulim)")
s.close()

from flask import Flask, render_template_string

app = Flask(__name__)

@app.route('/')
def index():
    return render_template_string("""
    <h2 style="color:#1976d2;">SIMULIMI PO FUNKSIONON!</h2>
    <div style="font-family:monospace;">
        [TEST] Komanda 'T' u dërgua te droni! (simulim)<br>
        [TEST] Detektimi i objektit dhe komanda dërguar! (simulim)<br>
        Komanda 'T' u dërgua përmes WiFi!
    </div>
    """)

if __name__ == '__main__':
    app.run(debug=True)
