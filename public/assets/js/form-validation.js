// Custom form validation messages in Vietnamese for required fields
(function () {
  'use strict';

  const DEFAULT_MESSAGES = {
    valueMissing: 'Vui lòng điền thông tin vào trường này.',
    typeMismatch: {
      email: 'Vui lòng nhập địa chỉ email hợp lệ.',
      url: 'Vui lòng nhập đường dẫn hợp lệ.',
      tel: 'Vui lòng nhập số điện thoại hợp lệ.'
    },
    tooShort: 'Trường này chưa đủ độ dài tối thiểu.',
    tooLong: 'Trường này vượt quá độ dài cho phép.',
    patternMismatch: 'Giá trị chưa đúng định dạng yêu cầu.',
    rangeUnderflow: 'Giá trị đang nhỏ hơn mức tối thiểu.',
    rangeOverflow: 'Giá trị đang lớn hơn mức tối đa.',
    stepMismatch: 'Giá trị chưa phù hợp với bước nhảy cho phép.'
  };

  const clearMessage = (field) => {
    field.setCustomValidity('');
  };

  const applyMessage = (field) => {
    if (field.validity.valid) {
      clearMessage(field);
      return;
    }

    if (field.validity.valueMissing) {
      field.setCustomValidity(field.getAttribute('data-msg-required') || DEFAULT_MESSAGES.valueMissing);
      return;
    }

    if (field.validity.typeMismatch) {
      const type = field.type || field.getAttribute('type') || 'text';
      const customTypeMessage = DEFAULT_MESSAGES.typeMismatch[type];
      field.setCustomValidity(field.getAttribute('data-msg-type') || customTypeMessage || DEFAULT_MESSAGES.valueMissing);
      return;
    }

    if (field.validity.tooShort) {
      field.setCustomValidity(field.getAttribute('data-msg-too-short') || DEFAULT_MESSAGES.tooShort);
      return;
    }

    if (field.validity.tooLong) {
      field.setCustomValidity(field.getAttribute('data-msg-too-long') || DEFAULT_MESSAGES.tooLong);
      return;
    }

    if (field.validity.patternMismatch) {
      field.setCustomValidity(field.getAttribute('data-msg-pattern') || DEFAULT_MESSAGES.patternMismatch);
      return;
    }

    if (field.validity.rangeUnderflow) {
      field.setCustomValidity(field.getAttribute('data-msg-min') || DEFAULT_MESSAGES.rangeUnderflow);
      return;
    }

    if (field.validity.rangeOverflow) {
      field.setCustomValidity(field.getAttribute('data-msg-max') || DEFAULT_MESSAGES.rangeOverflow);
      return;
    }

    if (field.validity.stepMismatch) {
      field.setCustomValidity(field.getAttribute('data-msg-step') || DEFAULT_MESSAGES.stepMismatch);
      return;
    }

    field.setCustomValidity(DEFAULT_MESSAGES.valueMissing);
  };

  const handleInvalid = (event) => {
    const field = event.target;
    if (!(field instanceof HTMLElement)) {
      return;
    }
    event.preventDefault();
    applyMessage(field);
    if (typeof field.reportValidity === 'function') {
      field.reportValidity();
    }
  };

  const attachHandlers = () => {
    document.addEventListener('invalid', handleInvalid, true);

    const forms = document.querySelectorAll('form');
    forms.forEach((form) => {
      if (!form.hasAttribute('novalidate')) {
        form.setAttribute('novalidate', 'novalidate');
      }
    });

    const fields = document.querySelectorAll('input, textarea, select');
    fields.forEach((field) => {
      if (field.dataset.jfValidated === 'true') {
        return;
      }
      field.dataset.jfValidated = 'true';

      field.addEventListener('input', () => {
        clearMessage(field);
      });

      field.addEventListener('change', () => {
        clearMessage(field);
        if (!field.validity.valid) {
          applyMessage(field);
        }
      });

      field.addEventListener('blur', () => {
        if (!field.validity.valid) {
          applyMessage(field);
        } else {
          clearMessage(field);
        }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attachHandlers);
  } else {
    attachHandlers();
  }
})();
