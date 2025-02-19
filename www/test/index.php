<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php"); ?>

<? $APPLICATION->IncludeComponent(
    "astra:calendar.export.import",
    "",  
); ?>

<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>