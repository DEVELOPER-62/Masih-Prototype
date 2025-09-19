<?php
// === Bagian OCR ===
require_once(__DIR__ . "/fpdf.php"); // library PDF (download dari fpdf.org)
class PDF extends FPDF { function Header(){} function Footer(){} }

$resultText = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gambar'])) {
    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileTmp = $_FILES['gambar']['tmp_name'];
    $fileName = time() . "_" . basename($_FILES['gambar']['name']);
    $targetFile = $uploadDir . $fileName;
    $lang = $_POST['bahasa'] ?? "eng";

    if (move_uploaded_file($fileTmp, $targetFile)) {
        $outputFile = $uploadDir . "output_" . time();
        $cmd = "tesseract " . escapeshellarg($targetFile) . " " . escapeshellarg($outputFile) . " -l " . escapeshellarg($lang);
        exec($cmd);

        $txtFile = $outputFile . ".txt";
        $resultText = file_exists($txtFile) ? file_get_contents($txtFile) : "⚠️ Gagal membaca teks dari gambar.";
    } else {
        $resultText = "⚠️ Gagal upload file.";
    }
}

// === Export hasil OCR ===
if (isset($_GET['export']) && isset($_GET['data'])) {
    $text = urldecode($_GET['data']);
    $format = $_GET['export'];

    if ($format === "txt") {
        header("Content-Type: text/plain");
        header("Content-Disposition: attachment; filename=hasil_ocr.txt");
        echo $text; exit;
    } elseif ($format === "html") {
        header("Content-Type: text/html");
        header("Content-Disposition: attachment; filename=hasil_ocr.html");
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Hasil OCR</title></head><body><pre>" . htmlspecialchars($text) . "</pre></body></html>";
        exit;
    } elseif ($format === "pdf") {
        $pdf = new PDF();
        $pdf->AddPage();
        $pdf->SetFont("Arial", "", 12);
        $pdf->MultiCell(0, 10, $text);
        $pdf->Output("D", "hasil_ocr.pdf");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Picture To Text</title>
  <style>
    body {
      margin: 0;
      font-family: "Segoe UI", Arial, sans-serif;
      transition: background 0.5s, color 0.5s;
    }
    body.dark {
      background: #111;
      color: #fff;
    }
    body.light {
      background: #f9f9f9;
      color: #111;
    }
    h1 {
      margin: 20px;
      padding: 15px 30px;
      border-radius: 12px;
      font-weight: bold;
      text-align: center;
      background: #8ee5f8d0;
      color: #111;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
    form {
      margin: 20px auto;
      padding: 20px;
      border-radius: 12px;
      background: rgba(255,255,255,0.1);
      max-width: 600px;
      text-align: center;
    }
    input[type="file"], select {
      margin: 10px 0;
      padding: 10px;
      border-radius: 8px;
      border: none;
      width: 80%;
    }
    button {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      background: #8ee5f8;
      color: #111;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }
    button:hover { background: #6fcfe2; transform: scale(1.05); }
    .result {
      margin: 20px auto;
      padding: 15px;
      background: #fff;
      color: #111;
      border-radius: 12px;
      max-width: 700px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    .tools {
      margin-top: 10px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .tools a, .tools button {
      flex: 1;
      font-size: 14px;
      padding: 8px;
      text-align: center;
      text-decoration: none;
      background: #8ee5f8;
      color: #111;
      border-radius: 6px;
      transition: 0.3s;
    }
    .tools a:hover { background: #6fcfe2; }
    .preview {
      margin-top: 15px;
      max-width: 300px;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.4);
    }
    .toggle-mode {
      position: fixed;
      top: 15px;
      right: 15px;
      padding: 8px 14px;
      border-radius: 8px;
      cursor: pointer;
      font-weight: bold;
      border: none;
      background: #8ee5f8;
      color: #111;
      box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    }
  </style>
</head>
<body class="dark">
  <button class="toggle-mode" onclick="toggleMode()">🌙/☀️ Mode</button>
  <h1>📷 AI Salin Teks Gambar</h1>

  <!-- Form OCR -->
  <form method="POST" enctype="multipart/form-data">
    <input type="file" name="gambar" id="gambar" accept="image/*" required onchange="previewImage(event)"><br>
    <select name="bahasa" required>
      <option value="eng">English</option>
      <option value="ind">Indonesia</option>
      <option value="jpn">日本語 (Jepang)</option>
      <option value="deu">Deutsch (Jerman)</option>
      <option value="fra">Français (Prancis)</option>
    </select><br>
    <button type="submit">🔍 Proses Gambar</button>
    <br>
    <img id="preview" class="preview" style="display:none;" />
  </form>

  <?php if (!empty($resultText)): ?>
    <div class="result">
      <h2>📑 Hasil OCR:</h2>
      <p id="ocr-text"><?= nl2br(htmlspecialchars($resultText)) ?></p>
      <div class="tools">
        <button onclick="copyText()">📋 Salin</button>
        <a href="?export=txt&data=<?= urlencode($resultText) ?>">⬇️ TXT</a>
        <a href="?export=pdf&data=<?= urlencode($resultText) ?>">⬇️ PDF</a>
        <a href="?export=html&data=<?= urlencode($resultText) ?>">⬇️ HTML</a>
      </div>
    </div>
  <?php endif; ?>

  <script>
    function previewImage(event) {
      const img = document.getElementById('preview');
      img.style.display = "block";
      img.src = URL.createObjectURL(event.target.files[0]);
    }
    function copyText() {
      const text = document.getElementById("ocr-text").innerText;
      navigator.clipboard.writeText(text).then(() => {
        alert("✅ Teks berhasil disalin!");
      });
    }
    function toggleMode() {
      const body = document.body;
      if (body.classList.contains("dark")) {
        body.classList.remove("dark");
        body.classList.add("light");
      } else {
        body.classList.remove("light");
        body.classList.add("dark");
      }
    }
  </script>
</body>
</html>