document.addEventListener('DOMContentLoaded', function () {
	var toggles = document.querySelectorAll('.toggle-password');

	toggles.forEach(function (toggle) {
		toggle.addEventListener('click', function () {
			var input = toggle.closest('.input-group')?.querySelector('.js-password');
			if (!input) {
				return;
			}

			var isHidden = input.getAttribute('type') === 'password';
			input.setAttribute('type', isHidden ? 'text' : 'password');
			toggle.setAttribute('aria-label', isHidden ? 'Ẩn mật khẩu' : 'Hiển thị mật khẩu');

			var icon = toggle.querySelector('i');
			if (icon) {
				icon.classList.toggle('fa-eye');
				icon.classList.toggle('fa-eye-slash');
			}
		});
	});
});
