<?php
$currentMonth = date('F');
echo Date('m', strtotime($currentMonth . " last month"));
