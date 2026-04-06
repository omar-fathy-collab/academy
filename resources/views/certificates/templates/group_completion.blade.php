<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.4, maximum-scale=1.0, minimum-scale=0.3, user-scalable=no">
    <title>Certificate of Achievement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&display=swap" rel="stylesheet">
    
  <style>
        :root {
            --brand-navy: 204 79% 20%;
            --brand-gold:  36 90% 53%;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            /* Allow scrolling in all directions */
            overflow: auto;
            /* Keep original certificate size */
            min-width: 800px;
            padding: 20px;
            /* Allow zooming */
            touch-action: manipulation;
            -webkit-text-size-adjust: 100%;
        }

        .certificate-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            /* Allow zooming */
            transform-origin: center;
        }

        .certificate-a4 {
            width: 21cm;
            height: 29.7cm;
            background: white;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
            overflow: hidden;
            /* Maintain aspect ratio */
            aspect-ratio: 21 / 29.7;
        }

        .certificate-border {
            position: absolute;
            top: 40px;
            left: 40px;
            right: 40px;
            bottom: 40px;
            border: 3px solid hsl(var(--brand-navy));
            border-radius: 8px;
            z-index: 1;
        }

        .certificate-inner-border {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid hsl(var(--brand-gold));
            border-radius: 4px;
            z-index: 2;
        }

        .certificate-content {
            position: relative;
            z-index: 10;
            padding: 70px 60px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .header {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .academy-brand {
            display: flex;
            top: 5px;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .academy-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 10px;
            background: white;
            padding: 8px;
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.2);
            border: 2px solid rgba(245, 145, 23, 255);
        }

        .academy-text {
            text-align: center;
            flex: 1;
        }

        .academy-logo {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: hsl(var(--brand-navy));
            text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
        }
        
        .academy-subtitle {
            font-size: 14px;
            color: hsl(var(--brand-gold));
            
        }

        .certificate-title {
            font-family: 'Cinzel', serif;
            font-size: 48px;
            font-weight: 700;
            color: hsl(var(--brand-navy));
            letter-spacing: 4px;
            margin: 20px 0 10px;
        }

        .certificate-subtitle {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: hsl(var(--brand-gold));
            font-weight: 600;
            letter-spacing: 2px;
        }

        .presented-to {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
        }

        .recipient-name {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: hsl(var(--brand-gold));
            font-weight: 700;
            margin: 20px 0;
            padding: 10px 0;
            border-top: 2px solid #eee;
            border-bottom: 2px solid #eee;
        }

        .certificate-body {
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .certificate-text {
            font-size: 18px;
            line-height: 1.6;
            color: #444;
            max-width: 600px;
            margin: 0 auto;
        }

        .highlight {
            color: hsl(var(--brand-navy));
            font-weight: 600;
        }

        .recognition {
            font-style: italic;
            color: #666;
            margin-top: 20px;
            font-size: 16px;
        }

        .performance-grid {
            margin: 40px 0;
            padding: 30px;
            background: linear-gradient(135deg, #fff9e6, #fff5d6);
            border-radius: 12px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            position: relative;
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.1);
        }
        
        .performance-item {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(184, 134, 11, 0.15);
            border: 1px solid rgba(245, 145, 23, 255);
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .performance-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(184, 134, 11, 0.2);
        }
        
        .performance-label {
            font-size: 13px;
            color: #8b6914;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: block;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .performance-value {
            font-size: 20px;
            font-weight: 700;
            color: #5d4a0f;
        }

        .signature-area {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }

        .signature-box {
            text-align: center;
            flex: 1;
            max-width: 200px;
        }

        .signature-line {
            width: 180px;
            height: 1px;
            background: #333;
            margin: 60px auto 10px;
        }

        .signature-name {
            font-weight: 600;
            font-size: 16px;
            color: hsl(var(--brand-navy));
        }

        .signature-title {
            font-size: 14px;
            color: #666;
        }

        .seal {
            width: 120px;
            height: 120px;
            position: relative;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 12px;
            font-weight: bold;
            color: white;
            text-align: center;
        }

        .seal-text {
            position: absolute;
            z-index: 2;
            pointer-events: none;
        }

        .decoration {
            position: absolute;
            z-index: 0;
        }

        .corner-top-left {
            top: 50px;
            left: 50px;
            width: 80px;
            height: 80px;
            border-top: 3px solid hsl(var(--brand-gold));
            border-left: 3px solid hsl(var(--brand-gold));
        }

        .corner-top-right {
            top: 50px;
            right: 50px;
            width: 80px;
            height: 80px;
            border-top: 3px solid hsl(var(--brand-gold));
            border-right: 3px solid hsl(var(--brand-gold));
        }

        .corner-bottom-left {
            bottom: 50px;
            left: 50px;
            width: 80px;
            height: 80px;
            border-bottom: 3px solid hsl(var(--brand-gold));
            border-left: 3px solid hsl(var(--brand-gold));
        }

        .corner-bottom-right {
            bottom: 50px;
            right: 50px;
            width: 80px;
            height: 80px;
            border-bottom: 3px solid hsl(var(--brand-gold));
            border-right: 3px solid hsl(var(--brand-gold));
        }

        .gold-strip {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background: hsl(var(--brand-gold));
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #888;
        }

        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: hsl(var(--brand-navy));
            color: white;
        }

        .btn-secondary {
            background: transparent;
            color: hsl(var(--brand-navy));
            border: 2px solid hsl(var(--brand-navy));
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .certificate-number {
            position: absolute;
            top: 30px;
            right: 43%;
            font-size: 13px;
            color: hsl(var(--brand-navy));
            background: white;
            padding: 10px 18px;
            border: 1px solid hsl(var(--brand-gold));
            font-weight: 600;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(184, 134, 11, 0.15);
            z-index: 3;
        }

        /* QR Code improvements */
        .qr-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .qr-code {
            width: 100px;
            height: 100px;
            border: 2px solid hsl(var(--brand-gold));
            border-radius: 8px;
            padding: 5px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .qr-label {
            font-size: 10px;
            color: hsl(var(--brand-navy));
            font-weight: 600;
            text-align: center;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .certificate-a4 {
                box-shadow: none;
                margin: 0;
                width: 21cm;
                height: 29.7cm;
            }
            
            .no-print {
                display: none;
            }
            
            .controls {
                display: none;
            }
        }

        /* Remove all previous responsive tweaks */
        /* Keep original certificate size on all screens */
        @media (max-width: 900px) {
            body {
                padding: 10px;
                /* Allow scrolling in all directions */
                overflow: auto;
                /* Keep original certificate size */
                min-width: 800px;
                -webkit-text-size-adjust: none;
                text-size-adjust: none;
            }
            
            .certificate-container {
                max-width: 100%;
                /* Allow zooming */
                transform-origin: top left;
            }
            
            .certificate-a4 {
                width: 21cm;
                height: 29.7cm;
                /* Maintain aspect ratio */
                aspect-ratio: 21 / 29.7;
            }
        }

        /* Remove all tweaks for very small screens */
        /* Keep original certificate size on all screens */
        @media (max-width: 480px) {
            body {
                padding: 10px;
                /* Allow scrolling in all directions */
                overflow: auto;
                /* Keep original certificate size */
                min-width: 800px;
            }
            
            .certificate-container {
                max-width: 100%;
                /* Allow zooming */
                transform-origin: top left;
            }
            
            .certificate-a4 {
                width: 21cm;
                height: 29.7cm;
                /* Maintain aspect ratio */
                aspect-ratio: 21 / 29.7;
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-a4" id="certificate">
            <div class="gold-strip"></div>

            <div class="certificate-border"></div>
            <div class="certificate-inner-border"></div>
            
            <div class="decoration corner-top-left"></div>
            <div class="decoration corner-top-right"></div>
            <div class="decoration corner-bottom-left"></div>
            <div class="decoration corner-bottom-right"></div>
            
            <div class="certificate-content">
                <div class="header">
                    {{-- Image on left --}}
                    @if(file_exists(public_path('assets/ictacademy.jpeg')))
                        <img src="{{ asset('assets/ictacademy.jpeg') }}" alt="{{ config('app.name', 'Shefae') }}" class="academy-img" />
                    @else
                        <div class="academy-img" style="display:flex;align-items:center;justify-content:center;font-weight:700;color:#8b6914;font-size:24px;">
                            {{ strtoupper(substr(config('app.name', 'Shefae'),0,2)) }}
                        </div>
                    @endif

                    {{-- Text in middle --}}
                    <div class="academy-text">
                        <div class="academy-logo">SHEFAE</div>
                        <div class="academy-subtitle">Learn How To Learn</div>
                    </div>

                    {{-- QR on right --}}
                    <div class="qr-container">
                        @php
                            $domain = request()->getSchemeAndHttpHost();
                            $certificateUrl = "{$domain}/certificates/{$certificate->id}/preview-design/group_completion";
                            $qr = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($certificateUrl);
                        @endphp
                        <img src="{{ $qr }}" alt="QR Code" class="qr-code">
                    </div>
                </div>

                <div class="certificate-body">
                    <div class="presented-to font-bold">This certificate is presented to</div>
                    <div class="recipient-name">
                        {{ $certificate->user ? $certificate->user->name : 'Student Name' }}
                    </div>
                    
                    <div class="certificate-text">
                        for successfully completing the 
                        <span class="highlight">
                            @php
                                $group = $certificate->group ?? (isset($certificate->group_id) ? \App\Models\Group::find($certificate->group_id) : null);
                                $course = $certificate->course ?? ($group && isset($group->course_id) ? \App\Models\Course::find($group->course_id) : null);
                                $courseName = $course ? ($course->course_name ?? $course->name ?? 'Training Program') : 'Training Program';
                            @endphp
                            {{ $courseName }}
                        </span>
                        @if($group)
                            <br>Group: <span class="highlight">{{ $group->group_name }}</span>
                        @endif
                    </div>
                    
                  

                        <div class="performance-item">
                            <div class="performance-label">Date of Completion</div>
                            <div class="performance-value">
                                {{ $certificate->issue_date ? $certificate->issue_date->format('F d, Y') : 'N/A' }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                
                <div class="signature-area">
                    <div class="seal">
                        <svg width="120" height="120" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" class="absolute">
                            <defs>
                                <!-- Golden radial gradient -->
                                <radialGradient id="goldenRays" cx="50%" cy="50%" r="50%">
                                    <stop offset="60%" stop-color="hsl( 36 90% 53%)" stop-opacity="0" />
                                    <stop offset="85%" stop-color="hsl( 36 90% 53%)" stop-opacity="0.7" />
                                    <stop offset="100%" stop-color="hsl( 36 90% 53%)" stop-opacity="1" />
                                </radialGradient>

                                <!-- Generate rays (conic style) -->
                                <mask id="raysMask">
                                    <g>
                                        <!-- Create 60 rays -->
                                        <g transform="translate(60,60)">
                                            <!-- Each ray is a small white line in a different direction -->
                                            <g id="ray">
                                                <rect x="-1" y="-60" width="2" height="25" fill="white" />
                                            </g>
                                            <!-- Repeat ray at 60 angles -->
                                            <use href="#ray" transform="rotate(6)" />
                                            <use href="#ray" transform="rotate(12)" />
                                            <use href="#ray" transform="rotate(18)" />
                                            <use href="#ray" transform="rotate(24)" />
                                            <use href="#ray" transform="rotate(30)" />
                                            <use href="#ray" transform="rotate(36)" />
                                            <use href="#ray" transform="rotate(42)" />
                                            <use href="#ray" transform="rotate(48)" />
                                            <use href="#ray" transform="rotate(54)" />
                                            <use href="#ray" transform="rotate(60)" />
                                            <use href="#ray" transform="rotate(66)" />
                                            <use href="#ray" transform="rotate(72)" />
                                            <use href="#ray" transform="rotate(78)" />
                                            <use href="#ray" transform="rotate(84)" />
                                            <use href="#ray" transform="rotate(90)" />
                                            <use href="#ray" transform="rotate(96)" />
                                            <use href="#ray" transform="rotate(102)" />
                                            <use href="#ray" transform="rotate(108)" />
                                            <use href="#ray" transform="rotate(114)" />
                                            <use href="#ray" transform="rotate(120)" />
                                            <use href="#ray" transform="rotate(126)" />
                                            <use href="#ray" transform="rotate(132)" />
                                            <use href="#ray" transform="rotate(138)" />
                                            <use href="#ray" transform="rotate(144)" />
                                            <use href="#ray" transform="rotate(150)" />
                                            <use href="#ray" transform="rotate(156)" />
                                            <use href="#ray" transform="rotate(162)" />
                                            <use href="#ray" transform="rotate(168)" />
                                            <use href="#ray" transform="rotate(174)" />
                                            <use href="#ray" transform="rotate(180)" />
                                            <use href="#ray" transform="rotate(186)" />
                                            <use href="#ray" transform="rotate(192)" />
                                            <use href="#ray" transform="rotate(198)" />
                                            <use href="#ray" transform="rotate(204)" />
                                            <use href="#ray" transform="rotate(210)" />
                                            <use href="#ray" transform="rotate(216)" />
                                            <use href="#ray" transform="rotate(222)" />
                                            <use href="#ray" transform="rotate(228)" />
                                            <use href="#ray" transform="rotate(234)" />
                                            <use href="#ray" transform="rotate(240)" />
                                            <use href="#ray" transform="rotate(246)" />
                                            <use href="#ray" transform="rotate(252)" />
                                            <use href="#ray" transform="rotate(258)" />
                                            <use href="#ray" transform="rotate(264)" />
                                            <use href="#ray" transform="rotate(270)" />
                                            <use href="#ray" transform="rotate(276)" />
                                            <use href="#ray" transform="rotate(282)" />
                                            <use href="#ray" transform="rotate(288)" />
                                            <use href="#ray" transform="rotate(294)" />
                                            <use href="#ray" transform="rotate(300)" />
                                            <use href="#ray" transform="rotate(306)" />
                                            <use href="#ray" transform="rotate(312)" />
                                            <use href="#ray" transform="rotate(318)" />
                                            <use href="#ray" transform="rotate(324)" />
                                            <use href="#ray" transform="rotate(330)" />
                                            <use href="#ray" transform="rotate(336)" />
                                            <use href="#ray" transform="rotate(342)" />
                                            <use href="#ray" transform="rotate(348)" />
                                            <use href="#ray" transform="rotate(354)" />
                                        </g>
                                    </g>
                                </mask>
                            </defs>

                            <!-- Golden circle with rays -->
                            <circle cx="60" cy="60" r="58" fill="url(#goldenRays)" stroke="hsl( 36 90% 53%)" stroke-width="4" mask="url(#raysMask)" />

                            <!-- Inner blue circle -->
                            <circle cx="60" cy="60" r="35" fill="hsl(204, 79%, 20%)"/>
                        </svg>
                    </div>

                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name">
                            @if(!empty($certificate->instructor_name))
                                {{ $certificate->instructor_name }}
                            @elseif($certificate->group && $certificate->group->teacher)
                                @php
                                    $teacher = $certificate->group->teacher;
                                    $instructorName = $teacher->teacher_name ?? ($teacher->user->name ?? ($teacher->teacher ?? 'Instructor'));
                                @endphp
                                {{ $instructorName }}
                            @else
                                Instructor
                            @endif
                        </div>
                        <div class="signature-title">Course Instructor</div>
                    </div>
                    
                     <div class="seal">
                        <svg width="120" height="120" viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" class="absolute">
                            <defs>
                                <!-- Golden radial gradient -->
                                <radialGradient id="goldenRays" cx="50%" cy="50%" r="50%">
                                    <stop offset="60%" stop-color="hsl( 36 90% 53%)" stop-opacity="0" />
                                    <stop offset="85%" stop-color="hsl( 36 90% 53%)" stop-opacity="0.7" />
                                    <stop offset="100%" stop-color="hsl( 36 90% 53%)" stop-opacity="1" />
                                </radialGradient>

                                <!-- Generate rays (conic style) -->
                                <mask id="raysMask">
                                    <g>
                                        <!-- Create 60 rays -->
                                        <g transform="translate(60,60)">
                                            <!-- Each ray is a small white line in a different direction -->
                                            <g id="ray">
                                                <rect x="-1" y="-60" width="2" height="25" fill="white" />
                                            </g>
                                            <!-- Repeat ray at 60 angles -->
                                            <use href="#ray" transform="rotate(6)" />
                                            <use href="#ray" transform="rotate(12)" />
                                            <use href="#ray" transform="rotate(18)" />
                                            <use href="#ray" transform="rotate(24)" />
                                            <use href="#ray" transform="rotate(30)" />
                                            <use href="#ray" transform="rotate(36)" />
                                            <use href="#ray" transform="rotate(42)" />
                                            <use href="#ray" transform="rotate(48)" />
                                            <use href="#ray" transform="rotate(54)" />
                                            <use href="#ray" transform="rotate(60)" />
                                            <use href="#ray" transform="rotate(66)" />
                                            <use href="#ray" transform="rotate(72)" />
                                            <use href="#ray" transform="rotate(78)" />
                                            <use href="#ray" transform="rotate(84)" />
                                            <use href="#ray" transform="rotate(90)" />
                                            <use href="#ray" transform="rotate(96)" />
                                            <use href="#ray" transform="rotate(102)" />
                                            <use href="#ray" transform="rotate(108)" />
                                            <use href="#ray" transform="rotate(114)" />
                                            <use href="#ray" transform="rotate(120)" />
                                            <use href="#ray" transform="rotate(126)" />
                                            <use href="#ray" transform="rotate(132)" />
                                            <use href="#ray" transform="rotate(138)" />
                                            <use href="#ray" transform="rotate(144)" />
                                            <use href="#ray" transform="rotate(150)" />
                                            <use href="#ray" transform="rotate(156)" />
                                            <use href="#ray" transform="rotate(162)" />
                                            <use href="#ray" transform="rotate(168)" />
                                            <use href="#ray" transform="rotate(174)" />
                                            <use href="#ray" transform="rotate(180)" />
                                            <use href="#ray" transform="rotate(186)" />
                                            <use href="#ray" transform="rotate(192)" />
                                            <use href="#ray" transform="rotate(198)" />
                                            <use href="#ray" transform="rotate(204)" />
                                            <use href="#ray" transform="rotate(210)" />
                                            <use href="#ray" transform="rotate(216)" />
                                            <use href="#ray" transform="rotate(222)" />
                                            <use href="#ray" transform="rotate(228)" />
                                            <use href="#ray" transform="rotate(234)" />
                                            <use href="#ray" transform="rotate(240)" />
                                            <use href="#ray" transform="rotate(246)" />
                                            <use href="#ray" transform="rotate(252)" />
                                            <use href="#ray" transform="rotate(258)" />
                                            <use href="#ray" transform="rotate(264)" />
                                            <use href="#ray" transform="rotate(270)" />
                                            <use href="#ray" transform="rotate(276)" />
                                            <use href="#ray" transform="rotate(282)" />
                                            <use href="#ray" transform="rotate(288)" />
                                            <use href="#ray" transform="rotate(294)" />
                                            <use href="#ray" transform="rotate(300)" />
                                            <use href="#ray" transform="rotate(306)" />
                                            <use href="#ray" transform="rotate(312)" />
                                            <use href="#ray" transform="rotate(318)" />
                                            <use href="#ray" transform="rotate(324)" />
                                            <use href="#ray" transform="rotate(330)" />
                                            <use href="#ray" transform="rotate(336)" />
                                            <use href="#ray" transform="rotate(342)" />
                                            <use href="#ray" transform="rotate(348)" />
                                            <use href="#ray" transform="rotate(354)" />
                                        </g>
                                    </g>
                                </mask>
                            </defs>

                            <!-- Golden circle with rays -->
                            <circle cx="60" cy="60" r="58" fill="url(#goldenRays)" stroke="hsl( 36 90% 53%)" stroke-width="4" mask="url(#raysMask)" />

                            <!-- Inner blue circle -->
                            <circle cx="60" cy="60" r="35" fill="hsl(204, 79%, 20%)"/>
                        </svg>
                    </div>
                </div>
                
                <div class="footer">
                    <p>Issued by {{ $certificate->issuer ? $certificate->issuer->name : 'Shefae Administration' }} • Certificate ID: {{ $certificate->certificate_number }}</p>
                </div>
            </div>
        </div>

        <div class="controls no-print">
            
            <button id="downloadImageBtn" class="btn btn-secondary"><i class="fas fa-download"></i> Image</button>
            <button id="downloadPdfBtn" class="btn btn-primary"><i class="fas fa-file-pdf"></i> PDF</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
       <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        document.getElementById("downloadImageBtn").addEventListener("click", async () => {
            const certificate = document.getElementById("certificate");
            const canvas = await html2canvas(certificate, {
                scale: 3,
                useCORS: true,
                logging: false,
                scrollY: 0,
                windowWidth: certificate.scrollWidth,
                windowHeight: certificate.scrollHeight
            });
            const link = document.createElement("a");
            link.download = "certificate.png";
            link.href = canvas.toDataURL("image/png");
            link.click();
        });

        document.getElementById("downloadPdfBtn").addEventListener("click", async () => {
            const { jsPDF } = window.jspdf;
            const certificate = document.getElementById("certificate");

            // Capture high resolution image
            const canvas = await html2canvas(certificate, {
                scale: 3,
                useCORS: true,
                logging: false,
                scrollY: 0,
                windowWidth: certificate.scrollWidth,
                windowHeight: certificate.scrollHeight
            });

            const imgData = canvas.toDataURL("image/png");
            const pdf = new jsPDF({
                orientation: "portrait",
                unit: "mm",
                format: "a4"
            });

            // Image dimensions to fit A4 page without cropping
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imgWidth = pageWidth;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;

            pdf.addImage(imgData, "PNG", 0, 0, imgWidth, imgHeight);
            pdf.save("certificate.pdf");
        });
    </script>
</body>
</html>

