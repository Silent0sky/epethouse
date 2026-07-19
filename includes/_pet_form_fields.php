<?php
/**
 * _pet_form_fields.php — Shared form fields for pet Add / Edit.
 *
 * Expects variable $pf (pet row array or null) to be set before include.
 */
$pf = $pf ?? null;

$name    = $pf['name']    ?? '';
$species = $pf['species'] ?? '';
$breed   = $pf['breed']   ?? '';
$age     = $pf['age']     ?? '';
$weight  = $pf['weight']  ?? '';
$gender  = $pf['gender']  ?? '';
$notes   = $pf['notes']   ?? '';

$speciesOptions = ['dog', 'cat', 'bird', 'rabbit', 'fish', 'hamster'];
$genderOptions  = ['male', 'female'];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Pet Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($name) ?>" placeholder="e.g. Buddy">
    </div>
    <div class="col-md-6">
        <label class="form-label">Species <span class="text-danger">*</span></label>
        <select name="species" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($speciesOptions as $s): ?>
                <option value="<?= e($s) ?>" <?= $species === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Breed <span class="text-danger">*</span></label>
        <input type="text" name="breed" class="form-control" required value="<?= e($breed) ?>" placeholder="e.g. Labrador">
    </div>
    <div class="col-md-3">
        <label class="form-label">Age (years)</label>
        <input type="number" name="age" min="0" max="50" class="form-control" value="<?= e($age) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Weight (kg)</label>
        <input type="number" name="weight" min="0" step="0.1" class="form-control" value="<?= e($weight) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-select">
            <option value="">— Select —</option>
            <?php foreach ($genderOptions as $g): ?>
                <option value="<?= e($g) ?>" <?= $gender === $g ? 'selected' : '' ?>><?= e(ucfirst($g)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Pet Photo</label>
        <input type="file" name="avatar" class="form-control" accept="image/*">
        <?php if (!empty($pf['avatar'])): ?>
            <small class="text-muted">Current photo will be kept unless you upload a new one.</small>
        <?php endif; ?>
    </div>
    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2" placeholder="Behaviour, allergies, special care..."><?= e($notes) ?></textarea>
    </div>
</div>
