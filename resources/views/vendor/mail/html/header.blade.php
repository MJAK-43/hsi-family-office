@props(['url'])
<tr>
<td class="header">
<table class="header-band" align="center" width="600" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="header-cell">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/images/hsi-logo-email.png" width="168" alt="HSI — Héritage &amp; Sagesse International">
</a>
</td>
</tr>
<tr>
<td class="header-rule">&nbsp;</td>
</tr>
</table>
</td>
</tr>
