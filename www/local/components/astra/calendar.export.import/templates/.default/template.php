<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>
<div class="export-form">
    <div id="export-message"></div>
    <h2><?= GetMessage('EXPORTING_DATA'); ?></h2>
    <form id="exportForm">
        <label for="format"><?= GetMessage('SELECT_FORMAT'); ?></label>
        <select id="format" name="format">
            <option value="csv">CSV</option>
            <option value="xml">XML</option>
            <option value="json">JSON</option>
        </select>
        <button type="submit"><?= GetMessage('EXPORT'); ?></button>
    </form>
</div>

<hr>
<form id="importForm">
    <input type="file" id="fileInput">
    <button type="submit" id="importButton"><?= GetMessage('IMPORT'); ?></button>
</form>