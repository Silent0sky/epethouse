<?php
/**
 * admin/adoption.php — Adoption listings CRUD.
 *
 * Pets available for adoption are listed with thumbnail, species, breed, age,
 * status, vaccination & neuter flags. Admins can add, edit, delete listings.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'Adoption Listings';

// ─── POST handler ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id          = clean($_POST['id'] ?? '');
        $name        = clean($_POST['name'] ?? '');
        $species     = clean($_POST['species'] ?? '');
        $breed       = clean($_POST['breed'] ?? '');
        $age         = clean($_POST['age'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $status      = clean($_POST['status'] ?? 'available');
        $contactInfo = clean($_POST['contact_info'] ?? '');
        $vaccinated  = isset($_POST['vaccinated']) ? 1 : 0;
        $neutered    = isset($_POST['neutered'])   ? 1 : 0;

        if ($name === '' || $species === '' || $breed === '' || $description === '') {
            flash('danger', 'Name, species, breed and description are required.');
            redirect(APP_URL . '/admin/adoption.php');
        }
        if (!in_array($status, ['available', 'adopted', 'pending'], true)) {
            $status = 'available';
        }

        // Image upload (optional)
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $up = upload_file('image', 'pet_photo');
            if ($up['ok']) {
                $imagePath = $up['url'];
            } else {
                flash('danger', 'Image upload failed: ' . $up['error']);
                redirect(APP_URL . '/admin/adoption.php');
            }
        }

        if ($action === 'create') {
            $newId = gen_id('al_');
            db_insert('adoption_listings', [
                'id'           => $newId,
                'name'         => $name,
                'species'      => $species,
                'breed'        => $breed,
                'age'          => $age,
                'description'  => $description,
                'image'        => $imagePath,
                'vaccinated'   => $vaccinated,
                'neutered'     => $neutered,
                'status'       => $status,
                'contact_info' => $contactInfo !== '' ? $contactInfo : null,
            ]);
            flash('success', 'Adoption listing created.');
        } else {
            $existing = db_select_one('SELECT id, image FROM adoption_listings WHERE id = ? LIMIT 1', [$id]);
            if (!$existing) {
                flash('danger', 'Listing not found.');
                redirect(APP_URL . '/admin/adoption.php');
            }
            $fields = [
                'name'         => $name,
                'species'      => $species,
                'breed'        => $breed,
                'age'          => $age,
                'description'  => $description,
                'vaccinated'   => $vaccinated,
                'neutered'     => $neutered,
                'status'       => $status,
                'contact_info' => $contactInfo !== '' ? $contactInfo : null,
            ];
            if ($imagePath !== null) {
                $fields['image'] = $imagePath;
            }
            $set = [];
            $params = [];
            foreach ($fields as $k => $v) {
                $set[] = "`{$k}` = ?";
                $params[] = $v;
            }
            $params[] = $id;
            db_execute('UPDATE adoption_listings SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
            flash('success', 'Adoption listing updated.');
        }
        redirect(APP_URL . '/admin/adoption.php');
    }

    if ($action === 'delete') {
        $id = clean($_POST['id'] ?? '');
        db_execute('DELETE FROM adoption_listings WHERE id = ?', [$id]);
        flash('success', 'Adoption listing deleted.');
        redirect(APP_URL . '/admin/adoption.php');
    }

    flash('danger', 'Invalid request.');
    redirect(APP_URL . '/admin/adoption.php');
}

// ─── Data ────────────────────────────────────────────────────────────
include __DIR__ . '/../includes/header.php';
$listings = db_select(
    'SELECT id, name, species, breed, age, description, image,
            vaccinated, neutered, status, contact_info, created_at
       FROM adoption_listings
      ORDER BY
        FIELD(status, "available", "pending", "adopted"),
        created_at DESC'
);

/** Render a small thumbnail (image or paw icon). */
function adoption_thumb(?string $image, string $name): string
{
    if ($image && file_exists(__DIR__ . '/../' . $image)) {
        return '<img src="' . APP_URL . '/' . e($image) . '" alt="' . e($name) . '" class="rounded" style="width:48px;height:48px;object-fit:cover;">';
    }
    return '<span class="rounded d-inline-flex align-items-center justify-content-center bg-purple-soft text-purple" style="width:48px;height:48px;"><i class="fas fa-paw"></i></span>';
}

/** Status badge for adoption listings. */
function adoption_status_badge(string $status): string
{
    $map = [
        'available' => 'bg-success',
        'pending'   => 'bg-warning text-dark',
        'adopted'   => 'bg-info text-dark',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . e(ucfirst($status)) . '</span>';
}
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-heart me-2"></i>Adoption Listings
        </h1>
        <p class="text-muted mb-0">Manage pets available for adoption.</p>
    </div>
    <button type="button" class="btn btn-grad btn-sm mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#listingModal" id="newListingBtn">
        <i class="fas fa-plus me-1"></i> Add Listing
    </button>
</div>

<!-- Stats summary -->
<div class="row g-3 mb-4">
    <?php
    $availCount = count(array_filter($listings, fn($l) => $l['status'] === 'available'));
    $pendCount  = count(array_filter($listings, fn($l) => $l['status'] === 'pending'));
    $adopCount  = count(array_filter($listings, fn($l) => $l['status'] === 'adopted'));
    ?>
    <div class="col-4 col-md-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-success fs-3 fw-bold"><?= $availCount ?></div>
                <div class="small text-muted">Available</div>
            </div>
        </div>
    </div>
    <div class="col-4 col-md-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-warning fs-3 fw-bold"><?= $pendCount ?></div>
                <div class="small text-muted">Pending</div>
            </div>
        </div>
    </div>
    <div class="col-4 col-md-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-info fs-3 fw-bold"><?= $adopCount ?></div>
                <div class="small text-muted">Adopted</div>
            </div>
        </div>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header"><i class="fas fa-paw me-2 text-purple"></i>All Listings (<?= count($listings) ?>)</div>
    <div class="card-body p-0">
        <?php if (!$listings): ?>
            <div class="empty-state">
                <i class="fas fa-paw"></i>
                <p class="mb-0">No adoption listings yet. Click "Add Listing" to add a pet.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th></th><th>Name</th><th>Species</th><th>Breed</th>
                    <th>Age</th><th>Status</th><th>Vacc.</th><th>Neut.</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($listings as $l): ?>
                    <tr>
                        <td><?= adoption_thumb($l['image'] ?? null, $l['name']) ?></td>
                        <td>
                            <div class="fw-600"><?= e($l['name']) ?></div>
                            <small class="text-muted"><?= e(mb_strimwidth($l['description'], 0, 60, '…')) ?></small>
                        </td>
                        <td><?= e($l['species']) ?></td>
                        <td><?= e($l['breed']) ?></td>
                        <td><?= e($l['age']) ?></td>
                        <td><?= adoption_status_badge($l['status']) ?></td>
                        <td>
                            <?php if ((int)$l['vaccinated'] === 1): ?>
                                <i class="fas fa-syringe text-success" title="Vaccinated"></i>
                            <?php else: ?>
                                <i class="far fa-circle text-muted" title="Not vaccinated"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$l['neutered'] === 1): ?>
                                <i class="fas fa-check-circle text-success" title="Neutered"></i>
                            <?php else: ?>
                                <i class="far fa-circle text-muted" title="Not neutered"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-listing-btn"
                                    data-id="<?= e($l['id']) ?>"
                                    data-name="<?= e($l['name']) ?>"
                                    data-species="<?= e($l['species']) ?>"
                                    data-breed="<?= e($l['breed']) ?>"
                                    data-age="<?= e($l['age']) ?>"
                                    data-description="<?= e($l['description']) ?>"
                                    data-status="<?= e($l['status']) ?>"
                                    data-contact_info="<?= e($l['contact_info'] ?? '') ?>"
                                    data-vaccinated="<?= (int)$l['vaccinated'] ?>"
                                    data-neutered="<?= (int)$l['neutered'] ?>"
                                    data-image="<?= e($l['image'] ?? '') ?>"
                                    data-bs-toggle="modal" data-bs-target="#listingModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="post" action="<?= APP_URL ?>/admin/adoption.php" class="d-inline"
                                  onsubmit="return confirm('Delete this listing? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($l['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Create / Edit modal ─────────────────────────────────────────── -->
<div class="modal fade" id="listingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= APP_URL ?>/admin/adoption.php" enctype="multipart/form-data" id="listingForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="listingAction" value="create">
                <input type="hidden" name="id" id="listingId" value="">
                <div class="modal-header">
                    <h5 class="modal-title text-purple"><i class="fas fa-paw me-2"></i><span id="listingModalTitle">Add Adoption Listing</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Pet name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="fName" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Species <span class="text-danger">*</span></label>
                            <input type="text" name="species" id="fSpecies" class="form-control" required maxlength="40" list="speciesList" placeholder="Dog, Cat, Rabbit…">
                            <datalist id="speciesList">
                                <option value="Dog"><option value="Cat"><option value="Rabbit">
                                <option value="Bird"><option value="Fish"><option value="Hamster">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Breed <span class="text-danger">*</span></label>
                            <input type="text" name="breed" id="fBreed" class="form-control" required maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input type="text" name="age" id="fAge" class="form-control" maxlength="40" placeholder="e.g. 2 years, 6 months">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="fDescription" class="form-control" rows="4" required placeholder="Behaviour, history, special needs, etc."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" id="fStatus" class="form-select">
                                <option value="available">Available</option>
                                <option value="pending">Pending</option>
                                <option value="adopted">Adopted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact info</label>
                            <input type="text" name="contact_info" id="fContact" class="form-control" maxlength="200" placeholder="Phone / email / shelter address">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Photo</label>
                            <input type="file" name="image" accept="image/*" class="form-control">
                            <div id="currentImage" class="small text-muted mt-1"></div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end gap-4 pb-1">
                            <div class="form-check">
                                <input type="checkbox" name="vaccinated" id="fVaccinated" value="1" class="form-check-input">
                                <label for="fVaccinated" class="form-check-label"><i class="fas fa-syringe text-success me-1"></i>Vaccinated</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="neutered" id="fNeutered" value="1" class="form-check-input">
                                <label for="fNeutered" class="form-check-label"><i class="fas fa-check-circle text-success me-1"></i>Neutered</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal        = document.getElementById('listingModal');
    const form         = document.getElementById('listingForm');
    const actionInp    = document.getElementById('listingAction');
    const idInp        = document.getElementById('listingId');
    const modalTitle   = document.getElementById('listingModalTitle');
    const currentImg   = document.getElementById('currentImage');
    const fields = {
        name:        document.getElementById('fName'),
        species:     document.getElementById('fSpecies'),
        breed:       document.getElementById('fBreed'),
        age:         document.getElementById('fAge'),
        description: document.getElementById('fDescription'),
        status:      document.getElementById('fStatus'),
        contact:     document.getElementById('fContact'),
        vaccinated:  document.getElementById('fVaccinated'),
        neutered:    document.getElementById('fNeutered'),
    };

    document.getElementById('newListingBtn').addEventListener('click', () => {
        form.reset();
        actionInp.value = 'create';
        idInp.value = '';
        fields.status.value = 'available';
        fields.vaccinated.checked = false;
        fields.neutered.checked = false;
        modalTitle.textContent = 'Add Adoption Listing';
        currentImg.textContent = '';
    });

    modal.addEventListener('show.bs.modal', function (ev) {
        const trigger = ev.relatedTarget;
        if (!trigger || !trigger.classList.contains('edit-listing-btn')) return;
        actionInp.value = 'update';
        idInp.value          = trigger.dataset.id;
        fields.name.value        = trigger.dataset.name || '';
        fields.species.value     = trigger.dataset.species || '';
        fields.breed.value       = trigger.dataset.breed || '';
        fields.age.value         = trigger.dataset.age || '';
        fields.description.value = trigger.dataset.description || '';
        fields.status.value      = trigger.dataset.status || 'available';
        fields.contact.value     = trigger.dataset.contact_info || '';
        fields.vaccinated.checked = trigger.dataset.vaccinated === '1';
        fields.neutered.checked   = trigger.dataset.neutered === '1';
        modalTitle.textContent = 'Edit Adoption Listing';
        currentImg.textContent = trigger.dataset.image
            ? 'Current image: ' + trigger.dataset.image
            : '';
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php';
