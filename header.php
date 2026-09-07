<?php
function getHeader($PageTitle = '') {
    $headerGeneral = <<<EOD
    <header>
    <p>{$PageTitle}</p>
    </header>
    EOD;
    return $headerGeneral;
}
