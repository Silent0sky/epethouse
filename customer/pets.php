<?php
/**
 * customer/pets.php — Pet CRUD.
 *
 * List, add, edit, delete the customer's pets. Avatar stored as URL
 * string in pets.avatar (we accept uploads via upload_file()).
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$uid = $u['id'];

$speciesOptions = ['dog', 'cat', 'bird', 'rabbit', 'fish', 'hamster'];
$genderOptions  = ['male', 'female'];
$avatarColors   = ['bg-grad-purple', 'bg-grad-pink', 'bg-grad-teal', 'bg-grad-amber', 'bg-grad-blue', 'bg-grad-green'];

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $formType = $_POST['form_type'] ?? '';

    // ─── Add / Edit pet ───
    if ($formType === 'save_pet') {
        $petId   = trim($_POST['pet_id'] ?? '');
        $name    = clean($_POST['name'] ?? '');
        $species = clean($_POST['species'] ?? '');
        $breed   = clean($_POST['breed'] ?? '');
        $age     = ($_POST['age'] ?? '') !== '' ? (int) $_POST['age'] : null;
        $weight  = ($_POST['weight'] ?? '') !== '' ? (float) $_POST['weight'] : null;
        $gender  = clean($_POST['gender'] ?? '');
        $notes   = clean($_POST['notes'] ?? '');

        if ($name === '')                       flash('danger', 'Please enter your pet\'s name.');
        elseif (!in_array($species, $speciesOptions, true)) flash('danger', 'Please pick a valid species.');
        elseif ($breed === '')                  flash('danger', 'Please enter the breed.');
        elseif ($gender !== '' && !in_array($gender, $genderOptions, true)) flash('danger', 'Invalid gender.');
        else {
            $avatarUrl = null;
            if (!empty($_FILES['avatar']['name'])) {
                $up = upload_file('avatar', 'pet_photo');
                if ($up['ok']) $avatarUrl = $up['url'];
                else flash('warning', 'Avatar upload skipped: ' . $up['error']);
            }

            if ($petId !== '') {
                // Edit — make sure pet belongs to user
                $owned = db_scalar('SELECT id FROM pets WHERE id = ? AND user_id = ?', [$petId, $uid]);
                if (!$owned) {
                    flash('danger', 'Pet not found.');
                } else {
                    $fields = [
                        'name'    => $name,
                        'species' => $species,
                        'breed'   => $breed,
                        'age'     => $age,
                        'weight'  => $weight,
                        'gender'  => $gender ?: null,
                        'notes'   => $notes ?: null,
                    ];
                    if ($avatarUrl) $fields['avatar'] = $avatarUrl;
                    $set = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($fields)));
                    db_execute(
                        "UPDATE pets SET $set WHERE id = :_id AND user_id = :_uid",
                        array_merge($fields, ['_id' => $petId, '_uid' => $uid])
                    );
                    flash('success', 'Pet "' . e($name) . '" updated.');
                }
            } else {
                // Add
                $newId = gen_id('pet_');
                db_insert('pets', [
                    'id'      => $newId,
                    'user_id' => $uid,
                    'name'    => $name,
                    'species' => $species,
                    'breed'   => $breed,
                    'age'     => $age,
                    'weight'  => $weight,
                    'gender'  => $gender ?: null,
                    'avatar'  => $avatarUrl,
                    'notes'   => $notes ?: null,
                ]);
                notify($uid, 'Pet Added', 'You added a new pet — ' . $name . ' (' . $species . ').', 'general');
                flash('success', 'Pet "' . e($name) . '" added! 🐾');
            }
        }
        redirect(APP_URL . '/customer/pets.php');
    }

    // ─── Delete pet ───
    if ($formType === 'delete_pet') {
        $petId = $_POST['pet_id'] ?? '';
        $owned = db_select_one('SELECT id, name FROM pets WHERE id = ? AND user_id = ?', [$petId, $uid]);
        if (!$owned) {
            flash('danger', 'Pet not found.');
        } else {
            // Check for booking references
            $refs = (int) db_scalar(
                "SELECT COUNT(*) FROM (
                    SELECT id FROM grooming_bookings    WHERE pet_id = ?
                    UNION ALL
                    SELECT id FROM boarding_reservations WHERE pet_id = ?
                    UNION ALL
                    SELECT id FROM walking_bookings      WHERE pet_id = ?
                ) t",
                [$petId, $petId, $petId]
            );
            if ($refs > 0) {
                flash('warning', 'Cannot delete "' . e($owned['name']) . '" — it has ' . $refs . ' existing booking(s). Please cancel those first.');
            } else {
                db_execute('DELETE FROM pets WHERE id = ? AND user_id = ?', [$petId, $uid]);
                flash('success', 'Pet "' . e($owned['name']) . '" removed.');
            }
        }
        redirect(APP_URL . '/customer/pets.php');
    }
}

$pageTitle = 'My Pets';

$pets = db_select(
    'SELECT id, name, species, breed, age, weight, gender, avatar, notes, created_at
       FROM pets WHERE user_id = ? ORDER BY name ASC',
    [$uid]
);

$editPet = null;
if (isset($_GET['edit'])) {
    foreach ($pets as $p) {
        if ($p['id'] === $_GET['edit']) { $editPet = $p; break; }
    }
    if (!$editPet) {
        flash('warning', 'Pet not found.');
        redirect(APP_URL . '/customer/pets.php');
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-paw me-2"></i>My Pets</h1>
    <?php if (!$editPet): ?>
    <button class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#petModal" onclick="resetPetForm()">
        <i class="fas fa-plus me-1"></i>Add Pet
    </button>
    <?php endif; ?>
</div>

<?php if (!$pets): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="fas fa-paw"></i>
        <h4>No pets yet</h4>
        <p class="mb-3">Add your furry (or feathered) friends to book grooming, boarding and walking services for them.</p>
        <button class="btn btn-grad" data-bs-toggle="modal" data-bs-target="#petModal" onclick="resetPetForm()">
            <i class="fas fa-plus me-1"></i>Add Your First Pet
        </button>
    </div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($pets as $i => $p):
            $color = $avatarColors[$i % count($avatarColors)];
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <?php if (!empty($p['avatar'])): ?>
                            <img src="<?= APP_URL ?>/<?= e($p['avatar']) ?>" alt="<?= e($p['name']) ?>"
                                 style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <div class="stat-card <?= $color ?> d-flex align-items-center justify-content-center"
                                 style="width:60px;height:60px;border-radius:50%;padding:0;box-shadow:none;font-weight:700;font-size:1.2rem;">
                                <?= e(initials($p['name'])) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h5 class="mb-0"><?= e($p['name']) ?></h5>
                            <small class="text-muted text-capitalize"><?= e($p['species']) ?> · <?= e($p['breed']) ?></small>
                        </div>
                    </div>
                    <div class="row small mb-3">
                        <?php if ($p['age'] !== null): ?>
                        <div class="col-6 mb-1"><i class="fas fa-birthday-cake text-purple me-1"></i><?= (int)$p['age'] ?> year(s)</div>
                        <?php endif; ?>
                        <?php if ($p['weight'] !== null): ?>
                        <div class="col-6 mb-1"><i class="fas fa-weight-scale text-purple me-1"></i><?= e($p['weight']) ?> kg</div>
                        <?php endif; ?>
                        <?php if ($p['gender']): ?>
                        <div class="col-6 mb-1"><i class="fas fa-venus-mars text-purple me-1"></i><?= e(ucfirst($p['gender'])) ?></div>
                        <?php endif; ?>
                        <div class="col-6 mb-1"><i class="fas fa-calendar text-purple me-1"></i><?= e(fmt_date(substr($p['created_at'], 0, 10))) ?></div>
                    </div>
                    <?php if (!empty($p['notes'])): ?>
                    <p class="small text-muted border-start border-3 ps-2 mb-3"><?= e($p['notes']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex gap-2">
                        <a href="<?= APP_URL ?>/customer/pets.php?edit=<?= e($p['id']) ?>" class="btn btn-outline-primary btn-sm flex-fill">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <form method="post" action="<?= APP_URL ?>/customer/pets.php" class="d-inline"
                              data-confirm-submit="Delete this pet? This cannot be undone.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form_type" value="delete_pet">
                            <input type="hidden" name="pet_id" value="<?= e($p['id']) ?>">
                            <button class="btn btn-outline-danger btn-sm flex-fill">
                                <i class="fas fa-trash-alt me-1"></i>Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add Pet Modal -->
<div class="modal fade" id="petModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= APP_URL ?>/customer/pets.php" enctype="multipart/form-data" id="petForm">
        <?= csrf_field() ?>
        <input type="hidden" name="form_type" value="save_pet">
        <input type="hidden" name="pet_id" value="">
        <div class="modal-header bg-grad-purple text-white">
            <h5 class="modal-title"><i class="fas fa-paw me-2"></i>Add New Pet</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <?php include __DIR__ . '/../includes/_pet_form_fields.php'; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Pet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($editPet): ?>
<!-- Edit Pet (inline card instead of modal) -->
<div class="modal fade show" id="editPetModal" tabindex="-1" aria-hidden="true"
     style="display:block;background:rgba(0,0,0,0.5);" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="post" action="<?= APP_URL ?>/customer/pets.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="form_type" value="save_pet">
        <input type="hidden" name="pet_id" value="<?= e($editPet['id']) ?>">
        <div class="modal-header bg-grad-purple text-white">
            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit <?= e($editPet['name']) ?></h5>
            <a href="<?= APP_URL ?>/customer/pets.php" class="btn-close btn-close-white"></a>
        </div>
        <div class="modal-body">
            <?php
            $pf = $editPet;
            include __DIR__ . '/../includes/_pet_form_fields.php';
            ?>
        </div>
        <div class="modal-footer">
            <a href="<?= APP_URL ?>/customer/pets.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function resetPetForm() {
    const f = document.getElementById('petForm');
    if (!f) return;
    f.reset();
    f.querySelector('[name="pet_id"]').value = '';
    f.querySelector('.modal-title').innerHTML = '<i class="fas fa-paw me-2"></i>Add New Pet';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
