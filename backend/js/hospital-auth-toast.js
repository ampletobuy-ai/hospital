(function (window, document) {
  "use strict";

  var icons = {
    error: "✕",
    success: "✓",
    info: "i",
  };

  function removeToast(toast) {
    if (!toast || !toast.parentNode) {
      return;
    }
    toast.classList.add("is-leaving");
    window.setTimeout(function () {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 240);
  }

  function show(type, message, options) {
    if (!message) {
      return null;
    }

    options = options || {};
    var timeout = typeof options.timeout === "number" ? options.timeout : 5000;
    var existing = document.querySelector(".track-auth-toast");
    if (existing) {
      removeToast(existing);
    }

    var toast = document.createElement("div");
    toast.className = "track-auth-toast track-auth-toast--" + type;
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    var icon = document.createElement("span");
    icon.className = "track-auth-toast__icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = icons[type] || icons.info;

    var text = document.createElement("span");
    text.className = "track-auth-toast__text";
    text.textContent = message;

    toast.appendChild(icon);
    toast.appendChild(text);
    document.body.appendChild(toast);

    if (timeout > 0) {
      window.setTimeout(function () {
        removeToast(toast);
      }, timeout);
    }

    return toast;
  }

  window.HospitalAuthToast = {
    show: show,
    success: function (message, options) {
      return show("success", message, options);
    },
    error: function (message, options) {
      return show("error", message, options);
    },
    info: function (message, options) {
      return show("info", message, options);
    },
    dismiss: removeToast,
  };
})(window, document);
