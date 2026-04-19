<mjml>
  <mj-head>
    <mj-attributes>
      <mj-all font-family="Arial, Helvetica, sans-serif" />
      <mj-text font-size="15px" line-height="1.6" color="#374151" />
      <mj-button background-color="#4f46e5" border-radius="8px" font-size="15px" font-weight="600" inner-padding="12px 28px" />
    </mj-attributes>
    <mj-style>
      .footer-text div { color: #9ca3af !important; font-size: 12px !important; }
    </mj-style>
  </mj-head>
  <mj-body background-color="#f3f4f6">
    <mj-section padding="30px 0 10px">
      <mj-column>
        <mj-text align="center" font-size="22px" font-weight="700" color="#4f46e5">
          {{ config('app.name') }}
        </mj-text>
      </mj-column>
    </mj-section>

    <mj-section background-color="#ffffff" border-radius="12px" padding="32px 40px">
      <mj-column>
        @if($heading)
        <mj-text font-size="20px" font-weight="700" color="#111827" padding-bottom="16px">
          {!! nl2br(e($heading)) !!}
        </mj-text>
        @endif

        <mj-text>
          {!! nl2br(e($body)) !!}
        </mj-text>

        {{-- Only render the CTA when we have both a label and a safe URL.
             Safe = http(s) URL OR contains a template placeholder (which is
             substituted at render time and has its own validation upstream).
             Anything else (javascript:, data:, file:, etc.) is dropped. --}}
        @php
            $isSafeActionUrl = false;
            if (is_string($actionUrl) && $actionUrl !== '') {
                if (str_contains($actionUrl, '{{')) {
                    $isSafeActionUrl = true;
                } else {
                    $scheme = parse_url($actionUrl, PHP_URL_SCHEME);
                    $isSafeActionUrl = in_array($scheme, ['http', 'https'], true);
                }
            }
        @endphp
        @if($actionText && $isSafeActionUrl)
        <mj-button href="{{ $actionUrl }}" align="left" padding-top="20px">
          {{ $actionText }}
        </mj-button>
        @endif
      </mj-column>
    </mj-section>

    <mj-section padding="20px 0">
      <mj-column>
        <mj-text align="center" css-class="footer-text">
          &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
