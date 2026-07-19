<?php
/**
 * _address_form_fields.php — Shared address form fields (Add / Edit).
 *
 * Expects $af (address row array or null) before include.
 */
$af = $af ?? null;

$label    = $af['label']    ?? '';
$address  = $af['address']  ?? '';
$city     = $af['city']     ?? '';
$pincode  = $af['pincode']  ?? '';
$isDef    = (int) ($af['is_default'] ?? 0) === 1;
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Label <span class="text-danger">*</span></label>
        <input type="text" name="label" class="form-control" required value="<?= e($label) ?>" placeholder="Home / Office / Other">
    </div>
    <div class="col-md-6">
        <label class="form-label">City <span class="text-danger">*</span></label>
        <input type="text" name="city" class="form-control" required value="<?= e($city) ?>" placeholder="e.g. Chhatrapati Sambhajinagar">
    </div>
    <div class="col-12">
        <label class="form-label">Full Address <span class="text-danger">*</span></label>
        <textarea name="address" class="form-control" rows="2" required placeholder="House no, street, area, landmark"><?= e($address) ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Pincode <span class="text-danger">*</span></label>
        <input type="text" name="pincode" class="form-control" required pattern="[0-9]{6}" maxlength="6" value="<?= e($pincode) ?>" placeholder="6-digit pincode">
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="is_default" value="1" id="is_default" <?= $isDef ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_default">Set as default address</label>
        </div>
    </div>
</div>
