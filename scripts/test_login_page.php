<?php
$baseUrl = 'http://localhost/rpl/public';
$ch = curl_init("$baseUrl/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
echo substr($res, 0, 800);

