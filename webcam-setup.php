<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Webcam Setup | RP Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
  <style>
    video {
      width: 100%;
      border-radius: 10px;
    }
  </style>
</head>
<body class="p-4">
  <div class="container">
    <h3>🎥 Webcam Setup</h3>
    <p class="text-muted">Test your webcam and ensure it's working for attendance capture.</p>

    <video id="webcam" autoplay playsinline></video>
    <div class="mt-3">
      <button class="btn btn-primary" onclick="startWebcam()">Start Test</button>
      <button class="btn btn-danger" onclick="stopWebcam()">Stop Test</button>
    </div>
  </div>

  <script>
    let stream;

    async function startWebcam() {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        document.getElementById('webcam').srcObject = stream;
      } catch (err) {
        alert('Webcam not found or permission denied.');
      }
    }

    function stopWebcam() {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
      }
      document.getElementById('webcam').srcObject = null;
    }
  </script>
</body>
</html>
