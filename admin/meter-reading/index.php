<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'POS Meter Reading';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Meter Reading'],
    ['label' => 'POS Meter Reading'],
];
RBAC::requirePermission('readings.enter');
$apiUrl = API_URL;
$adminUrl = ADMIN_URL;
$today = date('Y-m-d');
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.consumer-card { border-left: 4px solid #181CB8; transition: all .2s; }
.consumer-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,.08); }
.reading-display { font-size: 2.5rem; font-weight: 700; color: #181CB8; }
.flag-high { color: #dc3545; }
.flag-low { color: #ffc107; }
.camera-preview { max-width: 100%; max-height: 300px; border-radius: 8px; }
#gpsStatus { font-size: .85rem; }
</style>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-tachometer-alt me-2 text-primary"></i>POS Meter Reading</h4>
        <p class="text-muted mb-0">Search consumer, enter reading, capture photo & GPS location</p>
    </div>
    <div class="d-flex gap-2">
        <a href="history.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-history me-1"></i>Reading History</a>
        <a href="meters.php" class="btn btn-outline-info btn-sm"><i class="fas fa-water-meter me-1"></i>Meters</a>
        <?php if (RBAC::can('readings.verify')): ?>
        <a href="verify.php" class="btn btn-outline-warning btn-sm"><i class="fas fa-check-double me-1"></i>Verify Readings</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-search me-2 text-primary"></i>Search Consumer</h5></div>
            <div class="card-body">
                <div class="offline-note alert alert-warning py-2 small d-none" id="offlineNote">
                    <i class="fas fa-wifi-slash me-1"></i> You appear offline. Readings will be queued.
                </div>
                <div class="mb-3">
                    <label class="form-label">Search by Consumer No, Name, Mobile, or Meter No</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="Type to search..." autocomplete="off" autofocus>
                        <button class="btn btn-primary" id="searchBtn"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="invalid-feedback" id="searchError"></div>
                </div>

                <div id="consumerInfo" class="d-none">
                    <hr>
                    <div class="consumer-card p-3 rounded border">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0" id="dispConsumerName"></h6>
                            <span class="badge" id="dispConnectionType"></span>
                        </div>
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><td class="text-muted w-40">Consumer No</td><td class="fw-600" id="dispConsumerNo"></td></tr>
                            <tr><td class="text-muted">Mobile</td><td id="dispMobile"></td></tr>
                            <tr><td class="text-muted">Ward / Tole</td><td id="dispAddress"></td></tr>
                            <tr><td class="text-muted">Meter No</td><td class="fw-600" id="dispMeterNo"></td></tr>
                            <tr><td class="text-muted">Meter Type</td><td id="dispMeterType"></td></tr>
                            <tr><td class="text-muted">Last Reading</td><td><span class="fw-600" id="dispLastReading"></span> <small class="text-muted" id="dispLastReadingDate"></small></td></tr>
                            <tr><td class="text-muted">Meter Status</td><td><span id="dispMeterStatus"></span></td></tr>
                        </table>

                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button class="btn btn-sm btn-outline-secondary" id="btnShowHistory"><i class="fas fa-list me-1"></i>History</button>
                            <button class="btn btn-sm btn-outline-success" id="btnShowMap" data-lat="" data-lng=""><i class="fas fa-map-marker-alt me-1"></i>Map</button>
                        </div>
                    </div>
                </div>

                <div id="noConsumer" class="d-none text-center py-5 text-muted">
                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                    <p>No consumer selected. Search above to begin.</p>
                </div>

                <div id="recentReadings" class="d-none mt-3">
                    <h6 class="mb-2"><i class="fas fa-history me-1 text-muted"></i>Recent Readings</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="readingsTable">
                            <thead><tr><th>Date</th><th>Reading</th><th>Consumption</th><th>Status</th></tr></thead>
                            <tbody id="readingsBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-pen me-2 text-primary"></i>Enter Reading</h5></div>
            <div class="card-body">
                <form id="readingForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="consumer_id" id="consumerId">
                    <input type="hidden" name="meter_id" id="meterId">
                    <input type="hidden" name="latitude" id="latitude">
                    <input type="hidden" name="longitude" id="longitude">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Previous Reading</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-history"></i></span>
                                <input type="text" class="form-control" id="prevReading" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Reading <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tachometer-alt"></i></span>
                                <input type="number" step="0.01" min="0" class="form-control form-control-lg" id="currentReading" name="current_reading" placeholder="Enter meter reading" required>
                            </div>
                            <div class="invalid-feedback" id="readingError"></div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Consumption (Calculated)</label>
                            <div class="reading-display" id="dispConsumption">0.00</div>
                            <small class="text-muted" id="avgHint"></small>
                            <div id="consumptionFlag" class="mt-1"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reading Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="reading_date" id="readingDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estimated Reading</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_estimated" id="isEstimated" value="1">
                                <label class="form-check-label" for="isEstimated">Mark as estimated</label>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Meter Photo</label>
                            <div class="d-flex gap-2">
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" name="meter_photo" id="meterPhoto" accept="image/*" capture="environment">
                                </div>
                                <button type="button" class="btn btn-outline-secondary" id="cameraBtn" title="Capture using camera"><i class="fas fa-camera"></i></button>
                            </div>
                            <div class="mt-2" id="photoPreview"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">GPS Location</label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" id="captureGps"><i class="fas fa-map-pin me-1"></i> Capture GPS</button>
                                <button type="button" class="btn btn-outline-secondary" id="manualGpsBtn" title="Enter manually"><i class="fas fa-keyboard"></i></button>
                            </div>
                            <div id="gpsStatus" class="mt-1">
                                <span class="text-muted"><i class="fas fa-info-circle me-1"></i>Click "Capture GPS" to use device location</span>
                            </div>
                            <div id="manualGpsInput" class="d-none mt-1">
                                <div class="row g-1">
                                    <div class="col-6"><input type="text" class="form-control form-control-sm" id="manualLat" placeholder="Latitude"></div>
                                    <div class="col-6"><input type="text" class="form-control form-control-sm" id="manualLng" placeholder="Longitude"></div>
                                </div>
                            </div>
                            <input type="hidden" id="gpsAccuracy">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="2" placeholder="Any remarks about this reading..."></textarea>
                    </div>

                    <div id="noMeterAlert" class="alert alert-warning d-none mt-3 mb-0"></div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                            <i class="fas fa-save me-2"></i>Submit Reading
                        </button>
                        <button type="reset" class="btn btn-outline-secondary btn-lg" id="resetBtn">
                            <i class="fas fa-undo me-1"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="afterSubmitCard" class="card d-none mt-3 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Reading Submitted</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted">Previous Reading</td><td class="fw-600" id="resultPrev"></td></tr>
                            <tr><td class="text-muted">Current Reading</td><td class="fw-600" id="resultCurr"></td></tr>
                            <tr><td class="text-muted">Consumption</td><td class="fw-600" id="resultCons"></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6 d-flex flex-column justify-content-center align-items-center">
                        <a href="<?= ADMIN_URL ?>billing/generate.php?consumer_id=" class="btn btn-warning btn-lg mb-2" id="spotBillLink">
                            <i class="fas fa-file-invoice me-2"></i>Generate Spot Bill
                        </a>
                        <button class="btn btn-outline-primary" id="newReadingBtn">
                            <i class="fas fa-plus me-1"></i>Take Another Reading
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5><i class="fas fa-history me-2 text-primary"></i>Reading History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div class="table-responsive"><table class="table table-sm table-hover mb-0" id="historyTable"><thead><tr><th>Date</th><th>Reading</th><th>Consumption</th><th>Verified</th><th>Remarks</th></tr></thead><tbody id="historyBody"></tbody></table></div></div>
        </div>
    </div>
</div>

<div class="modal fade" id="mapModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header"><h5><i class="fas fa-map-marker-alt me-2 text-primary"></i>Meter Location</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div id="mapContainer" style="height:350px;border-radius:8px;"></div></div>
        </div>
    </div>
</div>

<div id="spinnerOverlay" class="d-none" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,.7);z-index:9999;align-items:center;justify-content:center;">
    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"><span class="visually-hidden">Loading...</span></div>
</div>

<?php
ob_start(); ?>
<script>
$(function() {
    const $searchInput = $('#searchInput');
    const $consumerInfo = $('#consumerInfo');
    const $noConsumer = $('#noConsumer');
    const $recentReadings = $('#recentReadings');
    const $readingForm = $('#readingForm');
    const $afterSubmit = $('#afterSubmitCard');

    let selectedConsumer = null;

    function showLoader(show) {
        $('#spinnerOverlay').toggle(show);
    }

    function searchConsumer(query) {
        if (query.length < 2) return;
        showLoader(true);
        $('#searchError').hide();
        $.get('<?= $apiUrl ?>get-consumer-for-reading.php', { q: query })
            .done(function(res) {
                if (res.success) {
                    selectedConsumer = res.data;
                    displayConsumer(res.data);
                } else {
                    resetForm();
                    $consumerInfo.addClass('d-none');
                    $noConsumer.removeClass('d-none').find('p').text(res.message);
                }
            })
            .fail(function(xhr) {
                const msg = xhr.responseJSON?.message || 'Search failed';
                $('#searchError').text(msg).show();
            })
            .always(function() { showLoader(false); });
    }

    function displayConsumer(data) {
        $noConsumer.addClass('d-none');
        $consumerInfo.removeClass('d-none');

        $('#dispConsumerName').text(data.full_name);
        $('#dispConsumerNo').text(data.consumer_no);
        $('#dispMobile').text(data.mobile || data.phone || '-');
        $('#dispAddress').text('Ward ' + data.ward_no + (data.tole ? ', ' + data.tole : ''));
        $('#dispMeterNo').text(data.meter_no || 'No meter assigned');
        $('#dispMeterType').text(data.meter_type ? data.meter_type.charAt(0).toUpperCase() + data.meter_type.slice(1) : '-');

        const lastReading = data.actual_last_reading || data.last_reading || 0;
        $('#dispLastReading').text(parseFloat(lastReading).toFixed(2));
        $('#dispLastReadingDate').text(data.last_reading_date ? '(' + data.last_reading_date + ')' : '');

        const mStatus = data.meter_status || 'no_meter';
        const statusBadges = { active: 'success', inactive: 'secondary', defective: 'danger', replaced: 'info', damaged: 'warning' };
        $('#dispMeterStatus').html('<span class="badge bg-' + (statusBadges[mStatus] || 'secondary') + '">' + mStatus + '</span>');

        const typeBadges = { household: 'primary', commercial: 'info', institutional: 'secondary' };
        $('#dispConnectionType').text(data.connection_type).attr('class', 'badge bg-' + (typeBadges[data.connection_type] || 'primary'));

        $('#btnShowMap').data('lat', data.meter_lat).data('lng', data.meter_lng);

        $('#consumerId').val(data.id);
        $('#meterId').val(data.meter_id || '');
        $('#prevReading').val(parseFloat(lastReading).toFixed(2));

        const hasActiveMeter = data.meter_id && data.meter_status === 'active';
        if (!hasActiveMeter) {
            $('#submitBtn').prop('disabled', true);
            $('#noMeterAlert').removeClass('d-none');
            if (!data.meter_id) {
                $('#noMeterAlert').html('<i class="fas fa-exclamation-triangle me-2"></i> No meter assigned to this consumer. <a href="<?= $adminUrl ?>meter-reading/meters.php" class="alert-link">Assign a meter first</a>.');
            } else {
                $('#noMeterAlert').html('<i class="fas fa-exclamation-triangle me-2"></i> Meter status is "' + mStatus + '". Only active meters can take readings.');
            }
        } else {
            $('#submitBtn').prop('disabled', false);
            $('#noMeterAlert').addClass('d-none');
        }

        if (data.avg_consumption > 0) {
            $('#avgHint').text('Avg consumption: ' + parseFloat(data.avg_consumption).toFixed(2));
        }

        if (data.readings && data.readings.length) {
            $recentReadings.removeClass('d-none');
            const tbody = $('#readingsBody').empty();
            data.readings.forEach(function(r) {
                tbody.append('<tr><td>' + r.reading_date + '</td><td>' + parseFloat(r.current_reading).toFixed(2) + '</td><td>' + parseFloat(r.consumption || 0).toFixed(2) + '</td><td>' + (r.is_verified ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Pending</span>') + '</td></tr>');
            });
        } else {
            $recentReadings.addClass('d-none');
        }

        $('#currentReading').val('').removeClass('is-invalid');
        $('#dispConsumption').text('0.00');
        $('#consumptionFlag').empty();
        calculateConsumption();
    }

    function resetForm() {
        $consumerInfo.addClass('d-none');
        $noConsumer.removeClass('d-none');
        $recentReadings.addClass('d-none');
        $afterSubmit.addClass('d-none');
        selectedConsumer = null;
        $('#readingForm')[0].reset();
        $('#consumerId, #meterId, #latitude, #longitude, #gpsAccuracy').val('');
        $('#submitBtn').prop('disabled', false);
        $('#noMeterAlert').addClass('d-none');
        $('#prevReading').val('');
        $('#dispConsumption').text('0.00');
        $('#consumptionFlag').empty();
        $('#avgHint').text('');
        $('#photoPreview').empty();
        $('#gpsStatus').html('<span class="text-muted"><i class="fas fa-info-circle me-1"></i>Click "Capture GPS" to use device location</span>');
        $('#manualGpsInput').addClass('d-none');
        $('#manualLat, #manualLng').val('');
        $('#readingDate').val('<?= $today ?>');
        $('#searchInput').val('').focus();
        $searchInput.removeClass('is-invalid');
        $('#searchError').hide();
    }

    function calculateConsumption() {
        const prev = parseFloat($('#prevReading').val()) || 0;
        const curr = parseFloat($('#currentReading').val()) || 0;
        if (curr >= prev) {
            const cons = curr - prev;
            $('#dispConsumption').text(cons.toFixed(2));
            const avg = selectedConsumer?.avg_consumption || 0;
            const flagEl = $('#consumptionFlag').empty();
            if (avg > 0 && cons > avg * 2) {
                flagEl.html('<span class="badge bg-danger flag-high"><i class="fas fa-exclamation-triangle me-1"></i>Unusually high consumption</span>');
            } else if (cons === 0 && prev > 0) {
                flagEl.html('<span class="badge bg-warning flag-low"><i class="fas fa-exclamation-circle me-1"></i>Zero consumption</span>');
            }
        } else {
            $('#dispConsumption').text('0.00');
            $('#currentReading').addClass('is-invalid');
            $('#readingError').text('Current reading cannot be less than previous reading (' + prev.toFixed(2) + ')');
        }
    }

    $('#currentReading').on('input', function() {
        $(this).removeClass('is-invalid');
        $('#readingError').text('');
        calculateConsumption();
    });

    let searchTimeout;
    $('#searchBtn').on('click', function() { searchConsumer($searchInput.val()); });
    $searchInput.on('keyup', function() {
        clearTimeout(searchTimeout);
        const val = $(this).val();
        if (val.length >= 2) {
            searchTimeout = setTimeout(function() { searchConsumer(val); }, 400);
        }
    });

    $('#btnShowHistory').on('click', function() {
        if (!selectedConsumer) return;
        const cid = selectedConsumer.id;
        const mid = selectedConsumer.meter_id;
        if (!mid) { alert('No meter assigned'); return; }
        $('.modal').modal('hide');
        showLoader(true);
        $.get('<?= $apiUrl ?>get-consumer-for-reading.php', { q: selectedConsumer.consumer_no })
            .done(function(res) {
                if (res.success && res.data.readings) {
                    const tbody = $('#historyBody').empty();
                    res.data.readings.forEach(function(r) {
                        tbody.append('<tr><td>' + r.reading_date + '</td><td>' + parseFloat(r.current_reading).toFixed(2) + '</td><td>' + parseFloat(r.consumption || 0).toFixed(2) + '</td><td>' + (r.is_verified ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning">No</span>') + '</td><td>' + (r.remarks || '-') + '</td></tr>');
                    });
                    $('#historyModal').modal('show');
                }
            })
            .always(function() { showLoader(false); });
    });

    $('#btnShowMap').on('click', function() {
        const lat = $(this).data('lat');
        const lng = $(this).data('lng');
        if (!lat || !lng) { alert('No GPS location recorded for this meter'); return; }
        $('#mapModal').modal('show');
        setTimeout(function() {
            const map = L.map('mapContainer').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' }).addTo(map);
            L.marker([lat, lng]).addTo(map).bindPopup('<strong>Meter:</strong> ' + (selectedConsumer?.meter_no || '') + '<br><strong>Consumer:</strong> ' + (selectedConsumer?.full_name || ''));
        }, 300);
    });

    $('#captureGps').on('click', function() {
        if (!navigator.geolocation) {
            $('#gpsStatus').html('<span class="text-danger"><i class="fas fa-times me-1"></i>GPS not supported. Enter manually.</span>');
            $('#manualGpsBtn').click();
            return;
        }
        const btn = $(this).prop('disabled', true).html('<spinner class="spinner-border spinner-border-sm me-1"></spinner>Capturing...');
        $('#gpsStatus').html('<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i>Acquiring GPS location...</span>');

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude.toFixed(7);
                const lng = pos.coords.longitude.toFixed(7);
                const acc = pos.coords.accuracy.toFixed(0);
                $('#latitude').val(lat);
                $('#longitude').val(lng);
                $('#gpsAccuracy').val(acc);
                $('#gpsStatus').html('<span class="text-success"><i class="fas fa-check-circle me-1"></i>Lat: ' + lat + ', Lng: ' + lng + ' (accuracy: ' + acc + 'm)</span>');
                btn.prop('disabled', false).html('<i class="fas fa-map-pin me-1"></i>Recapture GPS');
            },
            function(err) {
                let msg = 'GPS error';
                if (err.code === 1) msg = 'Location access denied. Enter manually.';
                else if (err.code === 2) msg = 'GPS unavailable. Enter manually.';
                else if (err.code === 3) msg = 'GPS timed out. Try again.';
                $('#gpsStatus').html('<span class="text-danger"><i class="fas fa-times me-1"></i>' + msg + '</span>');
                btn.prop('disabled', false).html('<i class="fas fa-map-pin me-1"></i>Capture GPS');
                $('#manualGpsBtn').click();
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
        );
    });

    $('#manualGpsBtn').on('click', function() {
        $('#manualGpsInput').toggleClass('d-none');
        if (!$('#manualGpsInput').hasClass('d-none')) {
            $('#manualLat').on('input', function() { $('#latitude').val($(this).val()); });
            $('#manualLng').on('input', function() { $('#longitude').val($(this).val()); });
        }
    });

    $('#meterPhoto').on('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) { $('#photoPreview').html('<img src="' + e.target.result + '" class="camera-preview img-thumbnail"><button type="button" class="btn btn-sm btn-outline-danger mt-1" id="removePhoto"><i class="fas fa-trash me-1"></i>Remove</button>'); };
            reader.readAsDataURL(file);
        }
    });
    $(document).on('click', '#removePhoto', function() { $('#meterPhoto').val(''); $('#photoPreview').empty(); });

    $('#cameraBtn').on('click', function() { $('#meterPhoto').click(); });

    function detectOffline() {
        const offline = !navigator.onLine;
        $('#offlineNote').toggleClass('d-none', !offline);
    }
    $(window).on('online offline', detectOffline);
    detectOffline();

    $('#readingForm').on('submit', function(e) {
        e.preventDefault();
        if (!selectedConsumer || !$('#meterId').val()) {
            alert('Please search and select a consumer with an active meter first.');
            return;
        }
        if (!selectedConsumer.meter_id || selectedConsumer.meter_status !== 'active') {
            alert('The selected meter is not active. Cannot enter reading.');
            return;
        }
        const curr = parseFloat($('#currentReading').val());
        const prev = parseFloat($('#prevReading').val()) || 0;
        if (isNaN(curr) || curr < 0) {
            $('#currentReading').addClass('is-invalid');
            $('#readingError').text('Please enter a valid current reading');
            return;
        }
        if (curr < prev) {
            $('#currentReading').addClass('is-invalid');
            $('#readingError').text('Current reading cannot be less than previous reading (' + prev.toFixed(2) + ')');
            return;
        }

        const formData = new FormData(this);
        const btn = $('#submitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

        $.ajax({
            url: '<?= $apiUrl ?>save-reading.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        })
        .done(function(res) {
            if (res.success) {
                $('#resultPrev').text(parseFloat(res.data.previous_reading).toFixed(2));
                $('#resultCurr').text(parseFloat(res.data.current_reading).toFixed(2));
                $('#resultCons').text(parseFloat(res.data.consumption).toFixed(2));
                $('#spotBillLink').attr('href', '<?= $adminUrl ?>billing/generate.php?consumer_id=' + res.data.consumer_id + '&reading_id=' + res.data.reading_id);
                $('#readingForm').addClass('d-none');
                $afterSubmit.removeClass('d-none');
            } else {
                alert(res.message || 'Failed to save reading');
            }
        })
        .fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
            alert(msg);
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Submit Reading');
        });
    });

    $('#newReadingBtn').on('click', function() {
        $afterSubmit.addClass('d-none');
        $('#readingForm').removeClass('d-none')[0].reset();
        $('#photoPreview').empty();
        $('#consumptionFlag').empty();
        $('#dispConsumption').text('0.00');
        $('#currentReading').val('').focus();
        $('#latitude, #longitude, #gpsAccuracy').val('');
        $('#gpsStatus').html('<span class="text-muted"><i class="fas fa-info-circle me-1"></i>Click "Capture GPS" to use device location</span>');
        if (selectedConsumer) {
            $('#consumerId').val(selectedConsumer.id);
            $('#meterId').val(selectedConsumer.meter_id);
            const lr = selectedConsumer.actual_last_reading || selectedConsumer.last_reading || 0;
            $('#prevReading').val(parseFloat(lr).toFixed(2));
        }
    });

    $('#resetBtn').on('click', function() { resetForm(); });
});
</script>
<?php
$extraJs = ob_get_clean();
require_once __DIR__ . '/../includes/footer.php';
?>
