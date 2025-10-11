const video = document.getElementById('video');
const captureButton = document.getElementById('capture');
const msg = document.getElementById('msg');

// Request webcam
navigator.mediaDevices.getUserMedia({ video: true })
  .then(stream => video.srcObject = stream)
  .catch(err => console.error("Camera error:", err));

function captureFrame() {
  const canvas = document.createElement('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);
  return canvas.toDataURL('image/jpeg');
}

captureButton.addEventListener('click', async () => {
  msg.textContent = "Processing...";
  const imageBase64 = captureFrame();
  const blob = await fetch(imageBase64).then(r => r.blob());
  const formData = new FormData();

  if (window.location.pathname === "/") {
    const name = document.getElementById('name').value;
    formData.append("name", name);
    formData.append("image", blob, "photo.jpg");
    const res = await fetch("/register", { method: "POST", body: formData });
    const data = await res.json();
    msg.textContent = data.message;
  } else {
    formData.append("image", blob, "photo.jpg");
    const res = await fetch("/check", { method: "POST", body: formData });
    const data = await res.json();
    msg.textContent = data.status === "success" ? `✅ ${data.name} marked present!` : data.message;
  }
});
