@php
    $brand = $site->team->brand();
@endphp
{{ $brand['name'] }}

Your website is ready

The website for {{ $site->lead->name }} has been handed to you. The live copy on our hosting has been taken down so you can host the files yourself.

Download your website (no login needed, works until {{ $expiresOn }}):
{{ $downloadUrl }}

The download is a ZIP of the finished site, one folder per page, plus robots.txt.

Questions? Reply to this email and it goes to {{ $brand['name'] }}.
