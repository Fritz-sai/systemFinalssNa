<?php
$s = "
                const safeTitle = (s.title || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(new RegExp(String.fromCharCode(34), 'g'), '&quot;').replace(new RegExp(String.fromCharCode(39), 'g'), '&#39;');
";
echo $s;
