(function () {
  "use strict";

  if (
    typeof window.wp === "undefined" ||
    typeof window.wp.media === "undefined"
  ) {
    return;
  }

  const pickerSelector = "[data-kplr-media-picker]";

  const initPicker = (picker) => {
    const input = picker.querySelector('input[type="hidden"]');
    if (!input) {
      return;
    }
    const button = picker.querySelector("[data-default-label]");
    const removeButton = picker.querySelector(".kplr-media-picker__remove");
    const preview = picker.querySelector("[data-kplr-media-preview]");

    let frame = null;

    const setValue = (value, html) => {
      input.value = value;
      if (preview) {
        preview.innerHTML = html || "";
      }
      if (removeButton) {
        removeButton.disabled = value === "";
      }
      if (button) {
        const replaceLabel = button.getAttribute("data-replace-label");
        const defaultLabel = button.getAttribute("data-default-label");
        button.textContent = value === "" ? defaultLabel : replaceLabel;
      }
    };

    const openFrame = () => {
      const multiple =
        button && button.getAttribute("data-multiple") === "true";
      if (frame) {
        frame.open();
        return;
      }

      frame = window.wp.media({
        multiple,
      });

      frame.on("select", () => {
        const selection = frame.state().get("selection");
        if (!selection) {
          return;
        }
        if (multiple) {
          const ids = [];
          const html = [];
          selection.each((attachment) => {
            ids.push(attachment.id);
            html.push(renderPreviewItem(attachment));
          });
          setValue(ids.join(","), html.join(""));
        } else {
          const attachment = selection.first();
          if (!attachment) {
            return;
          }
          setValue(String(attachment.id), renderPreviewItem(attachment));
        }
      });

      frame.open();
    };

    const renderPreviewItem = (attachment) => {
      const data = attachment.toJSON();
      if (data.sizes && data.sizes.thumbnail) {
        return (
          '<img src="' +
          data.sizes.thumbnail.url +
          '" alt="' +
          (data.alt || "") +
          '" />'
        );
      }
      if (data.type === "image") {
        return '<img src="' + data.url + '" alt="' + (data.alt || "") + '" />';
      }
      return (
        '<span class="kplr-media-picker__file">' +
        (data.filename || data.url) +
        "</span>"
      );
    };

    if (button) {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        openFrame();
      });
    }

    if (removeButton) {
      removeButton.addEventListener("click", (event) => {
        event.preventDefault();
        setValue("", "");
      });
    }
  };

  const initAll = () => {
    document.querySelectorAll(pickerSelector).forEach(initPicker);
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }
})();
