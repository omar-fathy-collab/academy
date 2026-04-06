<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Badge of Achievement</title>
<style>
    :root {
        --gold: #f9c80e;
        --royal-blue: #007bff;
        --pink: #ff6f91;
        --deep-blue: #1a237e;
        --white: #fff;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #e3f2fd, #fff3e0);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        padding: 20px;
    }

    .badge-container {
        position: relative;
        width: 350px;
        background: var(--white);
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        text-align: center;
        overflow: hidden;
        border: 4px solid var(--gold);
    }

    /* ✨ Cheerful Background */
    .badge-container::before {
        content: "";
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle at center, rgba(255,255,255,0.2) 0%, transparent 70%);
        transform: rotate(25deg);
        animation: shine 6s linear infinite;
    }

    @keyframes shine {
        from { transform: rotate(25deg) translateX(-100px); }
        to { transform: rotate(25deg) translateX(100px); }
    }

    /* 🎖 Top Ribbon */
    .ribbon {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        background: linear-gradient(90deg, var(--royal-blue), var(--pink));
        color: white;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 8px 0;
        font-size: 16px;
        text-transform: uppercase;
        box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    }

    /* 🌟 Star */
    .star {
        font-size: 40px;
        color: var(--gold);
        text-shadow: 0 0 10px rgba(249,200,14,0.8);
        margin: 20px 0 10px 0;
        animation: spin 4s linear infinite;
    }


    .badge-title {
        font-size: 24px;
        font-weight: 800;
        color: var(--deep-blue);
        margin-bottom: 5px;
    }

    .badge-subtitle {
        font-size: 14px;
        color: var(--pink);
        letter-spacing: 1px;
        margin-bottom: 20px;
    }

    .recipient-name {
        font-size: 20px;
        font-weight: bold;
        color: var(--royal-blue);
        background: linear-gradient(90deg, var(--gold), var(--pink));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 15px;
    }

    .reason {
        background: #fffaf2;
        border: 2px dashed var(--gold);
        border-radius: 10px;
        padding: 10px 15px;
        color: var(--deep-blue);
        font-style: italic;
        font-size: 14px;
        margin-bottom: 20px;
    }

    /* Details */
    .details {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .detail {
        flex: 1;
        min-width: 120px;
        background: #f1f8ff;
        border-radius: 8px;
        padding: 8px;
        margin: 5px;
    }

    .detail span {
        display: block;
        color: var(--deep-blue);
        font-weight: 600;
        font-size: 12px;
    }

    .detail small {
        color: #666;
        font-size: 11px;
    }

    /* 🖊 Signatures */
    .signatures {
        display: flex;
        justify-content: space-around;
        font-size: 12px;
        color: #444;
    }

    .signature-line {
        width: 80px;
        border-bottom: 1px solid #555;
        margin: 0 auto 5px auto;
    }

    /* 🥇 Medal */
    .medal {
        position: absolute;
        bottom: -25px;
        left: 50%;
        transform: translateX(-50%);
        width: 90px;
        height: 90px;
        background: radial-gradient(circle at 30% 30%, var(--gold), #c59b07);
        border-radius: 50%;
        border: 5px solid #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
        text-shadow: 0 0 5px rgba(0,0,0,0.3);
        font-size: 13px;
    }

    /* 📥 Download Button */
    .download-btn {
        background: linear-gradient(90deg, var(--royal-blue), var(--pink));
        color: white;
        border: none;
        border-radius: 30px;
        padding: 10px 25px;
        font-size: 14px;
        margin-top: 30px;
        cursor: pointer;
        transition: 0.3s;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    .download-btn:hover {
        transform: scale(1.05);
    }
    .signature-name{
        font-size: 9px;
        color: var(--royal-blue);
    }

</style>
</head>
<body>

<div class="badge-container" id="badge">
    <div class="ribbon">Shefae Award</div>

    <div class="star">⭐</div>
    <div class="badge-title">Badge of Achievement</div>
    <div class="badge-subtitle">Special Recognition</div>

    <div class="recipient-name">{{ $certificate->user ? $certificate->user->name : 'Student' }}</div>

    <div class="reason">
        {{ $certificate->remarks ?: 'For outstanding performance and excellence in learning.' }}
    </div>

    <div class="details">
        <div class="detail">
            <span>Issue Date</span>
            <small>{{ $certificate->issue_date ? $certificate->issue_date->format('M d, Y') : 'N/A' }}</small>
        </div>
        <div class="detail">
            <span>Course Instructor</span>
            <small>
                {{ $certificate->instructor_name }}
            </small>
        </div>
      
    </div>

    <div class="signatures">
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-title">Issued By </div>
            <div class="signature-name">
               {{ $certificate->issuer ? $certificate->issuer->name : 'Shefae Administration' }}
            </div>
        </div>
        <div class="signature-item">
            <div class="signature-line"></div>
            <div class="signature-title">Badge Number</div>
            <div class="signature-name">
                {{ $certificate->certificate_number }}    
            </div>
        </div>
    </div>

    <div class="medal">OFFICIAL<br>AWARD</div>
</div>

<button class="download-btn" id="downloadBtn">Download as Image</button>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.getElementById("downloadBtn").onclick = function() {
    html2canvas(document.getElementById("badge")).then(canvas => {
        const link = document.createElement("a");
        link.download = "award_badge.png";
        link.href = canvas.toDataURL();
        link.click();
    });
};
</script>

</body>
</html>
