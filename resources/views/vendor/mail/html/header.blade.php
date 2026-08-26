@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<table cellpadding="0" cellspacing="0" border="0" align="center" style="margin: 0 auto; display: inline-table;">
<tr>
<td style="vertical-align: middle; padding-right: 8px;">
<div style="width: 10px; height: 10px; border-radius: 50%; background-color: #18e299; display: inline-block; line-height: 10px; font-size: 0;">&nbsp;</div>
</td>
<td style="vertical-align: middle; font-family: 'Poppins', 'Inter', sans-serif; font-size: 20px; font-weight: 700; letter-spacing: -0.03em; color: #0a0b0f; line-height: 1;">
{{ (trim($slot) === 'Laravel' || trim($slot) === 'KobiConnect' || empty(trim($slot))) ? 'kobiconnect' : $slot }}
</td>
</tr>
</table>
</a>
</td>
</tr>
