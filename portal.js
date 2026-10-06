/**
 * UNIVERSITAS NUSANTARA MANDIRI - SISTEM INFORMASI AKADEMIK
 * Client-side Controller & AJAX Engine for Role Mahasiswa
 */

// 1. Tab Navigation Controller
function switchTab(tabId, el) {
  // Hide all tab contents
  const tabs = document.querySelectorAll('.tab-content');
  tabs.forEach(t => t.classList.remove('active'));

  // Show target tab
  const target = document.getElementById('tab-' + tabId);
  if (target) {
    target.classList.add('active');
  }

  // Update sidebar active menu
  const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
  navItems.forEach(item => item.classList.remove('active'));

  if (el && el.classList.contains('nav-item')) {
    el.classList.add('active');
  } else {
    // If triggered from quick menu card or user pill
    navItems.forEach(item => {
      const clickAttr = item.getAttribute('onclick') || '';
      if (clickAttr.includes("'" + tabId + "'")) {
        item.classList.add('active');
      }
    });
  }

  // Close mobile sidebar if open
  const sidebar = document.getElementById('sidebar');
  if (sidebar && sidebar.classList.contains('mobile-open')) {
    sidebar.classList.remove('mobile-open');
  }

  // Scroll to top
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 2. Mobile Sidebar Toggle
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  if (sidebar) {
    sidebar.classList.toggle('mobile-open');
  }
}

// 3. Notification Dropdown & Read State
function toggleNotifDropdown() {
  const dropdown = document.getElementById('notifDropdown');
  if (dropdown) {
    dropdown.classList.toggle('active');
  }
}

// Close notif dropdown on click outside
document.addEventListener('click', function(e) {
  const wrapper = document.querySelector('.notif-wrapper');
  const dropdown = document.getElementById('notifDropdown');
  if (wrapper && dropdown && !wrapper.contains(e.target)) {
    dropdown.classList.remove('active');
  }
});

function markAllNotifRead() {
  fetch('api.php?action=mark_notifications_read')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const badge = document.getElementById('notifBadgeCount');
        if (badge) badge.style.display = 'none';
        const unreads = document.querySelectorAll('.notif-item.unread');
        unreads.forEach(u => u.classList.remove('unread'));
      }
    })
    .catch(err => console.error(err));
}

// 4. Modal Helpers
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('active');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('active');
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const activeModals = document.querySelectorAll('.modal-overlay.active');
    activeModals.forEach(m => m.classList.remove('active'));
  }
});

// 5. Profil Mahasiswa: Update Kontak
function openEditProfileModal() {
  openModal('editProfileModal');
}

function handleUpdateProfile(e) {
  e.preventDefault();
  const phone = document.getElementById('inputPhone').value;
  const email = document.getElementById('inputEmail').value;
  const address = document.getElementById('inputAddress').value;

  const formData = new FormData();
  formData.append('action', 'update_profile');
  formData.append('phone', phone);
  formData.append('email', email);
  formData.append('address', address);

  fetch('api.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('profPhone').innerText = phone;
        document.getElementById('profEmail').innerText = email;
        document.getElementById('profAddress').innerText = address;
        closeModal('editProfileModal');
        alert(data.message);
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      alert('Gagal menghubungi server.');
    });
}

// 6. KRS Online: Pilih & Batalkan Mata Kuliah
function toggleKrsCourse(scheduleId) {
  const formData = new FormData();
  formData.append('action', 'toggle_krs_course');
  formData.append('schedule_id', scheduleId);

  fetch('api.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        // Refresh halaman untuk reload status tabel & kuota secara konsisten
        window.location.reload();
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      alert('Terjadi kesalahan jaringan.');
    });
}

// Konfirmasi Pengajuan KRS
function confirmSubmitKrs() {
  openModal('confirmKrsModal');
}

function executeSubmitKrs() {
  const formData = new FormData();
  formData.append('action', 'submit_krs');

  fetch('api.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.json())
    .then(data => {
      closeModal('confirmKrsModal');
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      closeModal('confirmKrsModal');
      alert('Gagal mengajukan KRS.');
    });
}

// 7. Jadwal Kuliah: Toggle Timetable / List
function toggleJadwalView(mode) {
  const grid = document.getElementById('jadwalGridView');
  const list = document.getElementById('jadwalListView');
  const btnGrid = document.getElementById('btnViewTimetable');
  const btnList = document.getElementById('btnViewList');

  if (mode === 'grid') {
    grid.style.display = 'grid';
    list.style.display = 'none';
    btnGrid.classList.add('active');
    btnList.classList.remove('active');
  } else {
    grid.style.display = 'none';
    list.style.display = 'block';
    btnGrid.classList.remove('active');
    btnList.classList.add('active');
  }
}

// 8. Filter KHS Semester
function filterKhsSemester(sem) {
  // KHS data demonstratif
  alert('Memuat data capaian KHS untuk Semester ' + sem + '.');
}

function downloadKhsPdf() {
  window.print();
}

// 9. Pembayaran Tagihan: Modal & Eksekusi
let activePaymentId = 0;

function openPaymentModal(paymentId, title, amount, vaNumber) {
  activePaymentId = paymentId;
  document.getElementById('payModalTitle').innerText = title;
  document.getElementById('payModalAmount').innerText = 'Rp ' + Number(amount).toLocaleString('id-ID');
  document.getElementById('payModalVa').innerText = vaNumber || '889210202401001';
  openModal('paymentModal');
}

function executePayment() {
  const method = document.getElementById('paymentMethodSelect').value;
  const formData = new FormData();
  formData.append('action', 'pay_invoice');
  formData.append('payment_id', activePaymentId);
  formData.append('payment_method', method);

  fetch('api.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.json())
    .then(data => {
      closeModal('paymentModal');
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      closeModal('paymentModal');
      alert('Gagal memproses transaksi.');
    });
}

function showReceiptModal(title, amountStr, receiptNum, paidDate) {
  document.getElementById('recNumber').innerText = receiptNum;
  document.getElementById('recDesc').innerText = title;
  document.getElementById('recDate').innerText = paidDate;
  document.getElementById('recAmount').innerText = 'Rp ' + amountStr;
  openModal('receiptModal');
}

// 10. Pengumuman: Filter Kategori
function filterAnnouncements(category) {
  const cards = document.querySelectorAll('.ann-card');
  cards.forEach(card => {
    const cardCat = card.getAttribute('data-category');
    if (category === 'Semua' || cardCat === category) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });

  // Update button active state
  const buttons = document.querySelectorAll('#tab-pengumuman .btn-outline');
  buttons.forEach(btn => {
    if (btn.innerText.includes(category)) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
}

// 11. Dokumen Akademik: Pratinjau & Download
function previewDocModal(title, type, date) {
  document.getElementById('prevDocTitle').innerText = 'Pratinjau: ' + type;
  document.getElementById('prevDocName').innerText = title;
  document.getElementById('prevDocMeta').innerText = 'Diterbitkan pada: ' + date + ' • Status: Terverifikasi Digital';
  openModal('previewDocModal');
}

function downloadDemoDoc(filename) {
  // Simulasi download berkas resmi
  const element = document.createElement('a');
  element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent("UNIVERSITAS NUSANTARA MANDIRI\nBERKAS AKADEMIK RESMI: " + filename + "\nStatus: Valid & Terverifikasi PDDIKTI."));
  element.setAttribute('download', filename);
  element.style.display = 'none';
  document.body.appendChild(element);
  element.click();
  document.body.removeChild(element);
}

// 12. Layanan Akademik: Submit Permohonan
function openRequestServiceModal() {
  openModal('requestServiceModal');
}

function handleRequestService(e) {
  e.preventDefault();
  const serviceType = document.getElementById('serviceTypeSelect').value;
  const purpose = document.getElementById('servicePurpose').value;
  const note = document.getElementById('serviceNote').value;

  const formData = new FormData();
  formData.append('action', 'request_service');
  formData.append('service_type', serviceType);
  formData.append('purpose', purpose);
  formData.append('note', note);

  fetch('api.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.json())
    .then(data => {
      closeModal('requestServiceModal');
      if (data.success) {
        alert(data.message);
        window.location.reload();
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      closeModal('requestServiceModal');
      alert('Gagal mengirimkan permohonan.');
    });
}

// 13. Pengaturan: Ganti Password
function handleChangePassword(e) {
  e.preventDefault();
  const oldPass = document.getElementById('oldPassword').value;
  const newPass = document.getElementById('newPassword').value;
  const confirmPass = document.getElementById('confirmPassword').value;

  if (newPass !== confirmPass) {
    alert('Kata sandi baru dan konfirmasi kata sandi tidak cocok.');
    return;
  }

  const formData = new FormData();
  formData.append('action', 'change_password');
  formData.append('old_password', oldPass);
  formData.append('new_password', newPass);
  formData.append('confirm_password', confirmPass);

  fetch('api.php', {
    method: 'POST',
    body: formData
  })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        document.getElementById('changePasswordForm').reset();
      } else {
        alert(data.message);
      }
    })
    .catch(err => {
      alert('Gagal menghubungi server.');
    });
}
