<?php

require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/middleware/PermissionMiddleware.php';

Session::start();

if (!Session::has('user_id')) {
    header('Location: login.php');
    exit;
}

if (!PermissionMiddleware::check('scheme.view', 'view')) {
    http_response_code(403);
    exit('Access Denied');
}

$companyId = (int) Session::get('company_id');
$userId    = (int) Session::get('user_id');
$db        = Database::connect();

$message = '';
$error   = '';
$editSchemeId = !empty($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): float
{
    return round((float)$value, 2);
}

$products = [];
$groups   = [];
$brands   = [];
$schemes  = [];
$editScheme = null;
$editRules  = [];

try {
    $stmt = $db->prepare("SELECT id, product_name, sku FROM products WHERE company_id = :company_id AND is_active = TRUE ORDER BY product_name");
    $stmt->execute(['company_id' => $companyId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT id, group_name FROM product_groups WHERE company_id = :company_id AND is_active = TRUE ORDER BY group_name");
    $stmt->execute(['company_id' => $companyId]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT id, brand_name FROM brands WHERE company_id = :company_id AND is_active = TRUE ORDER BY brand_name");
    $stmt->execute(['company_id' => $companyId]);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    $error = 'Master data load error: ' . $ex->getMessage();
}

/* ---------------------------------------------------------------
| Load scheme for View/Edit
---------------------------------------------------------------- */
if ($editSchemeId > 0) {
    try {
        $stmt = $db->prepare("SELECT id, scheme_name, scheme_type, start_date, end_date, is_active FROM schemes WHERE id = :id AND company_id = :company_id LIMIT 1");
        $stmt->execute(['id' => $editSchemeId, 'company_id' => $companyId]);
        $editScheme = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editScheme) {
            throw new Exception('Scheme not found.');
        }

        $stmt = $db->prepare("SELECT id, apply_on, product_id, group_id, brand_id, min_qty, benefit_type, discount_value, free_product_id, free_qty, gift_desc, is_active FROM scheme_rules WHERE scheme_id = :scheme_id ORDER BY id");
        $stmt->execute(['scheme_id' => $editSchemeId]);
        $editRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        $editSchemeId = 0;
        $editScheme = null;
        $editRules = [];
    }
}

/* ---------------------------------------------------------------
| Create / Update scheme
---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = strtolower(trim($_POST['form_action'] ?? 'create'));
        $schemeId = !empty($_POST['scheme_id']) ? (int)$_POST['scheme_id'] : 0;

        if (!PermissionMiddleware::check('scheme.create', 'create')) {
            throw new Exception('You do not have permission to create/update schemes.');
        }

        $schemeName = trim($_POST['scheme_name'] ?? '');
        $schemeType = strtoupper(trim($_POST['scheme_type'] ?? ''));
        $startDate  = trim($_POST['start_date'] ?? '');
        $endDate    = trim($_POST['end_date'] ?? '');
        $isActive   = isset($_POST['is_active']);

        if ($schemeName === '') throw new Exception('Scheme name is required.');
        if (!in_array($schemeType, ['PROMOTIONAL', 'LIFTING'], true)) throw new Exception('Invalid scheme type.');
        if ($startDate === '' || $endDate === '') throw new Exception('Start date and end date are required.');
        if ($endDate < $startDate) throw new Exception('End date cannot be before start date.');

        $applyOns        = $_POST['apply_on'] ?? [];
        $productIds      = $_POST['product_id'] ?? [];
        $groupIds        = $_POST['group_id'] ?? [];
        $brandIds        = $_POST['brand_id'] ?? [];
        $minQtys         = $_POST['min_qty'] ?? [];
        $benefitTypes    = $_POST['benefit_type'] ?? [];
        $discountValues  = $_POST['discount_value'] ?? [];
        $freeProductIds  = $_POST['free_product_id'] ?? [];
        $freeQtys        = $_POST['free_qty'] ?? [];
        $giftDescs       = $_POST['gift_desc'] ?? [];

        if (count($applyOns) === 0) throw new Exception('Please add at least one scheme rule.');

        $rules = [];
        foreach ($applyOns as $i => $rawApplyOn) {
            $applyOn = strtoupper(trim($rawApplyOn));
            $productId = !empty($productIds[$i]) ? (int)$productIds[$i] : null;
            $groupId = !empty($groupIds[$i]) ? (int)$groupIds[$i] : null;
            $brandId = !empty($brandIds[$i]) ? (int)$brandIds[$i] : null;
            $minQty = money($minQtys[$i] ?? 0);
            $benefitType = strtoupper(trim($benefitTypes[$i] ?? ''));
            $discountValue = money($discountValues[$i] ?? 0);
            $freeProductId = !empty($freeProductIds[$i]) ? (int)$freeProductIds[$i] : null;
            $freeQty = money($freeQtys[$i] ?? 0);
            $giftDesc = trim($giftDescs[$i] ?? '');

            if (!in_array($applyOn, ['PRODUCT', 'GROUP', 'BRAND', 'WHOLE_BILL'], true)) {
                throw new Exception('Invalid Apply On in rule ' . ($i + 1));
            }
            if ($applyOn === 'PRODUCT' && !$productId) throw new Exception('Please select product in rule ' . ($i + 1));
            if ($applyOn === 'GROUP' && !$groupId) throw new Exception('Please select product group in rule ' . ($i + 1));
            if ($applyOn === 'BRAND' && !$brandId) throw new Exception('Please select brand in rule ' . ($i + 1));

            if ($schemeType === 'LIFTING' && $minQty <= 0) {
                throw new Exception('Target quantity must be greater than zero in lifting rule ' . ($i + 1));
            }
            if ($schemeType === 'PROMOTIONAL' && $minQty < 0) {
                throw new Exception('Invalid target quantity in rule ' . ($i + 1));
            }

            if (!in_array($benefitType, ['DISCOUNT_PERCENT','DISCOUNT_AMOUNT','FREE_QTY','FREE_PRODUCT','GIFT'], true)) {
                throw new Exception('Invalid benefit in rule ' . ($i + 1));
            }
            if (in_array($benefitType, ['DISCOUNT_PERCENT','DISCOUNT_AMOUNT'], true) && $discountValue <= 0) {
                throw new Exception('Discount value must be greater than zero in rule ' . ($i + 1));
            }
            if ($benefitType === 'DISCOUNT_PERCENT' && $discountValue > 100) {
                throw new Exception('Discount percentage cannot exceed 100 in rule ' . ($i + 1));
            }
            if ($benefitType === 'FREE_QTY' && $freeQty <= 0) {
                throw new Exception('Free quantity must be greater than zero in rule ' . ($i + 1));
            }
            if ($benefitType === 'FREE_PRODUCT') {
                if (!$freeProductId) throw new Exception('Please select free product in rule ' . ($i + 1));
                if ($freeQty <= 0) throw new Exception('Free quantity must be greater than zero in rule ' . ($i + 1));
            }
            if ($benefitType === 'GIFT' && $giftDesc === '') {
                throw new Exception('Gift / other benefit is required in rule ' . ($i + 1));
            }

            $rules[] = [
                'apply_on' => $applyOn,
                'product_id' => $productId,
                'group_id' => $groupId,
                'brand_id' => $brandId,
                'min_qty' => $minQty,
                'benefit_type' => $benefitType,
                'discount_value' => $discountValue,
                'free_product_id' => $freeProductId,
                'free_qty' => $freeQty,
                'gift_desc' => $giftDesc !== '' ? $giftDesc : null
            ];
        }

        $db->beginTransaction();

        if ($action === 'update' && $schemeId > 0) {
            $stmt = $db->prepare("SELECT id FROM schemes WHERE id = :id AND company_id = :company_id FOR UPDATE");
            $stmt->execute(['id' => $schemeId, 'company_id' => $companyId]);
            if (!$stmt->fetchColumn()) throw new Exception('Scheme not found or access denied.');

            $stmt = $db->prepare("UPDATE schemes SET scheme_name = :scheme_name, scheme_type = :scheme_type, start_date = :start_date, end_date = :end_date, is_active = :is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND company_id = :company_id");
            $stmt->execute([
                'scheme_name' => $schemeName,
                'scheme_type' => $schemeType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $isActive,
                'id' => $schemeId,
                'company_id' => $companyId
            ]);

            $stmt = $db->prepare("DELETE FROM scheme_rules WHERE scheme_id = :scheme_id");
            $stmt->execute(['scheme_id' => $schemeId]);
            $savedId = $schemeId;
            $message = 'Scheme updated successfully with ' . count($rules) . ' rule(s) ✅';
        } else {
            $stmt = $db->prepare("INSERT INTO schemes (company_id, scheme_name, scheme_type, start_date, end_date, is_active, created_at, updated_at) VALUES (:company_id, :scheme_name, :scheme_type, :start_date, :end_date, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id");
            $stmt->execute([
                'company_id' => $companyId,
                'scheme_name' => $schemeName,
                'scheme_type' => $schemeType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $isActive
            ]);
            $savedId = (int)$stmt->fetchColumn();
            $message = 'Scheme saved successfully with ' . count($rules) . ' rule(s) ✅';
        }

        $ruleStmt = $db->prepare("INSERT INTO scheme_rules (scheme_id, apply_on, product_id, group_id, brand_id, min_qty, benefit_type, discount_value, free_product_id, free_qty, gift_desc, is_active, created_at, updated_at) VALUES (:scheme_id, :apply_on, :product_id, :group_id, :brand_id, :min_qty, :benefit_type, :discount_value, :free_product_id, :free_qty, :gift_desc, :is_active, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");

        foreach ($rules as $rule) {
            $ruleStmt->execute([
                'scheme_id' => $savedId,
                'apply_on' => $rule['apply_on'],
                'product_id' => $rule['product_id'],
                'group_id' => $rule['group_id'],
                'brand_id' => $rule['brand_id'],
                'min_qty' => $rule['min_qty'],
                'benefit_type' => $rule['benefit_type'],
                'discount_value' => $rule['discount_value'],
                'free_product_id' => $rule['free_product_id'],
                'free_qty' => $rule['free_qty'],
                'gift_desc' => $rule['gift_desc'],
                'is_active' => $isActive
            ]);
        }

        $db->commit();
        $editSchemeId = 0;
        $editScheme = null;
        $editRules = [];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $ex->getMessage();
    }
}

/* ---------------------------------------------------------------
| Load saved schemes
---------------------------------------------------------------- */
try {
    $stmt = $db->prepare("SELECT s.id, s.scheme_name, s.scheme_type, s.start_date, s.end_date, s.is_active, COUNT(sr.id) AS rule_count FROM schemes s LEFT JOIN scheme_rules sr ON sr.scheme_id = s.id WHERE s.company_id = :company_id GROUP BY s.id, s.scheme_name, s.scheme_type, s.start_date, s.end_date, s.is_active ORDER BY s.id DESC");
    $stmt->execute(['company_id' => $companyId]);
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    if ($error === '') $error = 'Scheme list error: ' . $ex->getMessage();
}

$productsJson = json_encode($products, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$groupsJson   = json_encode($groups, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$brandsJson   = json_encode($brands, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$editRulesJson = json_encode($editRules, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$today = date('Y-m-d');
$isEdit = $editScheme !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Scheme Master - VyaapaarOS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#111827}.header{background:#111827;color:#fff;padding:18px 30px}.header h1{margin:0}.container{width:1400px;max-width:calc(100% - 30px);margin:25px auto}.card{background:#fff;border-radius:10px;padding:22px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.05)}h2{margin-top:0}.back{display:inline-block;margin-bottom:15px;color:#2563eb;text-decoration:none}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}.field label{display:block;font-weight:bold;margin-bottom:6px}input,select{width:100%;padding:9px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}input[type=checkbox]{width:auto}.table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:1250px}th,td{border-bottom:1px solid #e5e7eb;padding:9px;text-align:left;vertical-align:middle}th{background:#f8fafc}.btn{border:0;border-radius:6px;padding:9px 14px;cursor:pointer;font-weight:bold}.btn-primary{background:#2563eb;color:#fff}.btn-success{background:#059669;color:#fff}.btn-danger{background:#dc2626;color:#fff}.btn-secondary{background:#6b7280;color:#fff}.btn-warning{background:#d97706;color:#fff}.actions{display:flex;gap:10px;margin-top:20px}.alert-success{background:#dcfce7;color:#166534;padding:12px;border-radius:7px;margin-bottom:20px}.alert-error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:7px;margin-bottom:20px}.badge{display:inline-block;padding:4px 8px;border-radius:20px;font-size:12px;font-weight:bold}.badge-promotional{background:#dbeafe;color:#1d4ed8}.badge-lifting{background:#fef3c7;color:#92400e}.badge-active{background:#dcfce7;color:#166534}.badge-inactive{background:#fee2e2;color:#991b1b}.small-text{font-size:12px;color:#6b7280}.hidden{display:none!important}.target-field{min-width:210px}.benefit-field{min-width:170px}.narrow{min-width:95px}.action-cell{white-space:nowrap}.edit-banner{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:12px;border-radius:7px;margin-bottom:20px}.empty{color:#6b7280;text-align:center;padding:18px}@media(max-width:1000px){.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="header"><h1>🏷️ Scheme Master</h1></div>
<div class="container">
<a class="back" href="dashboard.php">← Back to Dashboard</a>
<?php if($message!==''): ?><div class="alert-success"><?=e($message)?></div><?php endif; ?>
<?php if($error!==''): ?><div class="alert-error"><?=e($error)?></div><?php endif; ?>

<?php if($isEdit): ?>
<div class="edit-banner"><strong>✏️ Editing Scheme #<?= (int)$editScheme['id'] ?>:</strong> <?=e($editScheme['scheme_name'])?> — नीचे सभी rules दिख रहे हैं। बदलाव करके <strong>Update Scheme</strong> करें।</div>
<?php endif; ?>

<form method="POST" id="schemeForm">
<input type="hidden" name="form_action" value="<?= $isEdit ? 'update' : 'create' ?>">
<?php if($isEdit): ?><input type="hidden" name="scheme_id" value="<?= (int)$editScheme['id'] ?>"><?php endif; ?>

<div class="card">
<h2><?= $isEdit ? 'Edit Scheme' : 'Create Scheme' ?></h2>
<div class="grid">
<div class="field"><label>Scheme Name *</label><input type="text" name="scheme_name" value="<?=e($editScheme['scheme_name'] ?? '')?>" placeholder="Example: Diwali Scheme 2026" required></div>
<div class="field"><label>Scheme Type *</label><select name="scheme_type" id="schemeType" onchange="schemeTypeChanged()" required><option value="PROMOTIONAL" <?= (($editScheme['scheme_type'] ?? '')==='PROMOTIONAL')?'selected':'' ?>>Promotional / In-Bill</option><option value="LIFTING" <?= (($editScheme['scheme_type'] ?? '')==='LIFTING')?'selected':'' ?>>Lifting Scheme</option></select></div>
<div class="field"><label>Start Date *</label><input type="date" name="start_date" value="<?=e($editScheme['start_date'] ?? $today)?>" required></div>
<div class="field"><label>End Date *</label><input type="date" name="end_date" value="<?=e($editScheme['end_date'] ?? '')?>" required></div>
</div><br>
<label><input type="checkbox" name="is_active" value="1" <?= (!$isEdit || !empty($editScheme['is_active'])) ? 'checked' : '' ?>> Active</label>
</div>

<div class="card">
<h2>Scheme Rules</h2>
<p class="small-text">एक Scheme के अंदर कई अलग-अलग Product / Group / Brand rules बना सकते हैं। हर rule का अपना अलग benefit हो सकता है।</p>
<div class="table-wrap"><table><thead><tr><th>#</th><th>Apply On</th><th>Product / Group / Brand</th><th>Target Qty</th><th>Benefit</th><th>Discount Value</th><th>Free Product</th><th>Free Qty</th><th>Gift / Other Benefit</th><th>Action</th></tr></thead><tbody id="rulesBody"></tbody></table></div><br>
<button type="button" class="btn btn-primary" onclick="addRule()">+ Add Scheme Rule</button>
<div class="actions"><button type="submit" class="btn btn-success"><?= $isEdit ? 'Update Scheme' : 'Save Scheme' ?></button><?php if($isEdit): ?><a class="btn btn-secondary" href="schemes.php" style="text-decoration:none">Cancel Edit</a><?php endif; ?></div>
</div>
</form>

<div class="card">
<h2>Saved Schemes</h2>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Scheme Name</th><th>Type</th><th>Rules</th><th>Start Date</th><th>End Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php if(!$schemes): ?><tr><td colspan="8" class="empty">No schemes found.</td></tr><?php else: foreach($schemes as $scheme): ?>
<tr>
<td><?= (int)$scheme['id'] ?></td><td><strong><?=e($scheme['scheme_name'])?></strong></td>
<td><?php if($scheme['scheme_type']==='LIFTING'): ?><span class="badge badge-lifting">LIFTING</span><?php else: ?><span class="badge badge-promotional">PROMOTIONAL</span><?php endif; ?></td>
<td><?= (int)$scheme['rule_count'] ?></td><td><?=e($scheme['start_date'])?></td><td><?=e($scheme['end_date'])?></td>
<td><?php if($scheme['is_active']): ?><span class="badge badge-active">ACTIVE</span><?php else: ?><span class="badge badge-inactive">INACTIVE</span><?php endif; ?></td>
<td class="action-cell"><a class="btn btn-primary" href="schemes.php?edit_id=<?= (int)$scheme['id'] ?>" style="text-decoration:none">View / Edit</a></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
</div>
</div>

<script>
const products = <?= $productsJson ?: '[]' ?>;
const groups = <?= $groupsJson ?: '[]' ?>;
const brands = <?= $brandsJson ?: '[]' ?>;
const existingRules = <?= $editRulesJson ?: '[]' ?>;
let ruleIndex = 0;

function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function productOptions(){let h='<option value="">-- Select Product --</option>';products.forEach(p=>{h+=`<option value="${p.id}">${escapeHtml(p.product_name)}${p.sku?' - '+escapeHtml(p.sku):''}</option>`});return h;}
function groupOptions(){let h='<option value="">-- Select Product Group --</option>';groups.forEach(g=>{h+=`<option value="${g.id}">${escapeHtml(g.group_name)}</option>`});return h;}
function brandOptions(){let h='<option value="">-- Select Brand --</option>';brands.forEach(b=>{h+=`<option value="${b.id}">${escapeHtml(b.brand_name)}</option>`});return h;}

function addRule(data=null){
 const tbody=document.getElementById('rulesBody'); const tr=document.createElement('tr'); tr.className='rule-row';
 tr.innerHTML=`<td class="rule-number">${ruleIndex+1}</td><td><select name="apply_on[]" onchange="applyOnChanged(this)" required><option value="PRODUCT">Product</option><option value="GROUP">Product Group</option><option value="BRAND">Brand</option><option value="WHOLE_BILL">Whole Bill</option></select></td><td class="target-field"><select name="product_id[]" class="product-target">${productOptions()}</select><select name="group_id[]" class="group-target hidden">${groupOptions()}</select><select name="brand_id[]" class="brand-target hidden">${brandOptions()}</select></td><td><input type="number" name="min_qty[]" class="min-qty narrow" min="0" step="0.001" value="0"></td><td class="benefit-field"><select name="benefit_type[]" onchange="benefitChanged(this)" required><option value="DISCOUNT_PERCENT">Discount %</option><option value="DISCOUNT_AMOUNT">Discount ₹</option><option value="FREE_QTY">Free Qty</option><option value="FREE_PRODUCT">Free Product</option><option value="GIFT">Gift / Other</option></select></td><td><input type="number" name="discount_value[]" class="discount-value narrow" min="0" step="0.01" value="0" placeholder="₹ / %"></td><td><select name="free_product_id[]" class="free-product">${productOptions()}</select></td><td><input type="number" name="free_qty[]" class="free-qty narrow" min="0" step="0.001" value="0"></td><td><input type="text" name="gift_desc[]" class="gift-desc" placeholder="Gift / other benefit"></td><td><button type="button" class="btn btn-danger" onclick="removeRule(this)">Remove</button></td>`;
 tbody.appendChild(tr);
 const apply=tr.querySelector('select[name="apply_on[]"]');
 if(data){apply.value=data.apply_on||'PRODUCT';tr.querySelector('.product-target').value=data.product_id||'';tr.querySelector('.group-target').value=data.group_id||'';tr.querySelector('.brand-target').value=data.brand_id||'';tr.querySelector('.min-qty').value=data.min_qty||0;tr.querySelector('select[name="benefit_type[]"]').value=data.benefit_type||'DISCOUNT_PERCENT';tr.querySelector('.discount-value').value=data.discount_value||0;tr.querySelector('.free-product').value=data.free_product_id||'';tr.querySelector('.free-qty').value=data.free_qty||0;tr.querySelector('.gift-desc').value=data.gift_desc||'';}
 ruleIndex++; applyOnChanged(apply); benefitChanged(tr.querySelector('select[name="benefit_type[]"]')); schemeTypeChanged();
}
function applyOnChanged(select){const row=select.closest('tr');const p=row.querySelector('.product-target'),g=row.querySelector('.group-target'),b=row.querySelector('.brand-target');p.classList.add('hidden');g.classList.add('hidden');b.classList.add('hidden');p.disabled=true;g.disabled=true;b.disabled=true;if(select.value==='PRODUCT'){p.classList.remove('hidden');p.disabled=false}else if(select.value==='GROUP'){g.classList.remove('hidden');g.disabled=false}else if(select.value==='BRAND'){b.classList.remove('hidden');b.disabled=false}}
function benefitChanged(select){const row=select.closest('tr');const d=row.querySelector('.discount-value'),fp=row.querySelector('.free-product'),fq=row.querySelector('.free-qty'),g=row.querySelector('.gift-desc');d.disabled=true;fp.disabled=true;fq.disabled=true;g.disabled=true;if(select.value==='DISCOUNT_PERCENT'||select.value==='DISCOUNT_AMOUNT')d.disabled=false;if(select.value==='FREE_PRODUCT'){fp.disabled=false;fq.disabled=false}if(select.value==='FREE_QTY')fq.disabled=false;if(select.value==='GIFT')g.disabled=false;}
function schemeTypeChanged(){const type=document.getElementById('schemeType').value;document.querySelectorAll('#rulesBody tr').forEach(row=>{const q=row.querySelector('.min-qty');if(type==='PROMOTIONAL'){q.value=0;q.disabled=true}else{q.disabled=false}})}
function removeRule(btn){const row=btn.closest('tr');if(row)row.remove();renumberRules();}
function renumberRules(){document.querySelectorAll('#rulesBody .rule-row').forEach((r,i)=>{r.querySelector('.rule-number').textContent=i+1})}

document.getElementById('schemeForm').addEventListener('submit',function(ev){const rows=[...document.querySelectorAll('#rulesBody tr')];if(!rows.length){ev.preventDefault();alert('Please add at least one scheme rule.');return;}const type=document.getElementById('schemeType').value;for(const row of rows){const apply=row.querySelector('select[name="apply_on[]"]').value;const benefit=row.querySelector('select[name="benefit_type[]"]').value;const qty=parseFloat(row.querySelector('.min-qty').value||0);if(type==='LIFTING'&&qty<=0){ev.preventDefault();alert('Target quantity must be greater than zero in every lifting rule.');return;}if(apply==='PRODUCT'&&!row.querySelector('.product-target').value){ev.preventDefault();alert('Please select product in every Product rule.');return;}if(apply==='GROUP'&&!row.querySelector('.group-target').value){ev.preventDefault();alert('Please select product group in every Group rule.');return;}if(apply==='BRAND'&&!row.querySelector('.brand-target').value){ev.preventDefault();alert('Please select brand in every Brand rule.');return;}if((benefit==='DISCOUNT_PERCENT'||benefit==='DISCOUNT_AMOUNT')&&parseFloat(row.querySelector('.discount-value').value||0)<=0){ev.preventDefault();alert('Please enter discount value in every discount rule.');return;}if(benefit==='FREE_PRODUCT'&&(!row.querySelector('.free-product').value||parseFloat(row.querySelector('.free-qty').value||0)<=0)){ev.preventDefault();alert('Please select free product and free quantity.');return;}if(benefit==='FREE_QTY'&&parseFloat(row.querySelector('.free-qty').value||0)<=0){ev.preventDefault();alert('Please enter free quantity.');return;}if(benefit==='GIFT'&&!row.querySelector('.gift-desc').value.trim()){ev.preventDefault();alert('Please enter Gift / Other Benefit.');return;}}});

<?php if($isEdit): ?>
if(existingRules.length){existingRules.forEach(rule=>addRule(rule));}else{addRule();}
<?php else: ?>
addRule();
<?php endif; ?>
</script>
</body>
</html>
