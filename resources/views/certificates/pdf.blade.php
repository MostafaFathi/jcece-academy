<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 4mm; font-family: dejavusans, sans-serif; color: #333333; background: #ffffff; }
        .sheet { height: 184mm; border: 4mm solid #6B1D32; padding: 5mm 15mm 4mm; text-align: center; }
        .gold-top { height: 2mm; background: #FFD21E; margin: -5mm -15mm 3mm; }
        .logo { width: 29mm; height: auto; margin: 0 auto; }
        .eyebrow { color: #6B1D32; font-size: 8pt; letter-spacing: 2px; font-weight: bold; }
        h1 { margin: 1mm 0 0; font-size: 23pt; color: #6B1D32; font-weight: bold; }
        .arabic-title { margin: 0; color: #333333; font-size: 13pt; direction: rtl; }
        .rule { width: 45mm; border-top: 1.2mm solid #FFD21E; margin: 2mm auto; }
        .presented { margin-top: 1mm; font-size: 9pt; color: #696969; }
        .student { margin: 0 0 1mm; font-size: 22pt; color: #333333; font-weight: bold; }
        .statement { font-size: 9pt; color: #696969; }
        .course { margin: 1mm 0; font-size: 17pt; color: #6B1D32; font-weight: bold; }
        .details { width: 100%; margin-top: 3mm; border-collapse: collapse; font-size: 8pt; }
        .details td { width: 33%; vertical-align: middle; }
        .label { color: #696969; font-size: 7pt; text-transform: uppercase; letter-spacing: .5px; }
        .value { color: #333333; font-weight: bold; margin-top: .5mm; }
        .verification { border-left: .5mm solid #E6E6E6; text-align: center; }
        .verify-url { font-size: 5.5pt; line-height: 1.2; color: #696969; margin-top: .5mm; }
        .footer { margin-top: 3mm; color: #696969; font-size: 6.5pt; letter-spacing: .5px; }
        .brand-line { display: inline-block; width: 15mm; border-top: .8mm solid #FFD21E; margin: 0 3mm 1mm; }
    </style>
</head>
<body>
<div class="sheet">
    <div class="gold-top"></div>
    @if (is_file($logoPath))
        <img class="logo" src="{{ $logoPath }}" alt="JCEC Academy">
    @endif

    <div class="eyebrow">JCEC ACADEMY</div>
    <h1>Certificate of Completion</h1>
    <p class="arabic-title" lang="ar">شهادة إتمام دورة تدريبية</p>
    <div class="rule"></div>

    <div class="presented">This certificate is proudly presented to</div>
    <div class="student" dir="auto">{{ $certificate->student_name_snapshot }}</div>
    <div class="statement">for successfully completing all published lessons in</div>
    <div class="course" dir="auto">{{ $certificate->course_title_snapshot }}</div>

    <table class="details">
        <tr>
            <td>
                <div class="label">Instructor</div>
                <div class="value" dir="auto">{{ $certificate->instructor_name_snapshot ?: 'JCEC Academy' }}</div>
            </td>
            <td>
                <div class="label">Certificate number</div>
                <div class="value">{{ $certificate->certificate_number }}</div>
                <div class="label" style="margin-top: 2mm;">Completed / Issued</div>
                <div class="value">{{ $certificate->completed_at->format('Y-m-d') }} / {{ $certificate->issued_at->format('Y-m-d') }}</div>
            </td>
            <td class="verification">
                <barcode code="{{ $verificationUrl }}" type="QR" size="1.05" error="M" disableborder="1" />
                <div class="label">Scan to verify</div>
                <div class="verify-url">{!! nl2br(e(wordwrap($verificationUrl, 45, "\n", true))) !!}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        <span class="brand-line"></span>
        LEARN · BUILD · LEAD
        <span class="brand-line"></span><br>
        <span lang="ar" dir="rtl">أكاديمية الجزيرة للتدريب المهني</span>
    </div>
</div>
</body>
</html>
