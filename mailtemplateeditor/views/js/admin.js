/**
 * Mail Template Editor - back-office UI.
 * Content is sent to the server base64-encoded (UTF-8 bytes) so that no filter,
 * proxy or PHP input sanitising can alter Greek characters or HTML.
 */
(function ($) {
  'use strict';

  $(function () {
    var cfgEl = document.getElementById('mte-config');
    if (!cfgEl) {
      return;
    }
    var cfg = JSON.parse(cfgEl.textContent);
    var t = cfg.i18n;
    var state = {
      layout: cfg.layout,
      settings: cfg.settings,
      social: cfg.social || {},
      multilang: cfg.multilang || {},
      footerLang: cfg.idLang,
      previewLang: cfg.idLang,
      contentLang: cfg.idLang,
      filesLang: cfg.idLang,
      templates: [],
      current: null,
      filter: 'all',
      dirty: false,
      files: [],
      currentFile: null,
      editor: null,
      sourceMode: false
    };

    /* ---------------- helpers ---------------- */

    function b64(str) {
      var bytes = new TextEncoder().encode(str || '');
      var bin = '';
      for (var i = 0; i < bytes.length; i += 0x8000) {
        bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
      }
      return btoa(bin);
    }

    function esc(s) {
      return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    }

    function toast(msg, isError) {
      var $t = $('#mte-toast');
      $t.text(msg).toggleClass('mte-toast-error', !!isError).addClass('mte-toast-show');
      clearTimeout(toast.timer);
      toast.timer = setTimeout(function () { $t.removeClass('mte-toast-show'); }, isError ? 6000 : 2500);
    }

    function api(action, data) {
      data = data || {};
      data.action = action;
      return $.ajax({ url: cfg.ajaxUrl + '&action=' + encodeURIComponent(action), type: 'POST', data: data, dataType: 'json' })
        .then(function (res) {
          if (!res || !res.ok) {
            var msg = (res && res.message) || t.error;
            toast(msg, true);
            return $.Deferred().reject(msg).promise();
          }
          return res;
        }, function (xhr) {
          toast(t.error + ' ' + (xhr.status || '') + ' ' + (xhr.responseText || '').replace(/<[^>]+>/g, ' ').substr(0, 200), true);
          return $.Deferred().reject().promise();
        });
    }

    function writeFrame(iframe, html) {
      var doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open();
      doc.write(html);
      doc.close();
      setTimeout(function () {
        try {
          iframe.style.height = Math.max(500, doc.documentElement.scrollHeight + 20) + 'px';
        } catch (e) {}
      }, 120);
    }

    function langOptions($select, selected) {
      $select.empty();
      cfg.languages.forEach(function (l) {
        $select.append($('<option>').val(l.id).text(l.name + ' (' + l.iso + ')').prop('selected', l.id === selected));
      });
    }

    function debounce(fn, ms) {
      var timer;
      return function () {
        var args = arguments, self = this;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(self, args); }, ms);
      };
    }

    /* ---------------- DESIGN TAB ---------------- */

    var $fontSelect = $('#mte-font-select');
    $.each(cfg.fonts, function (key, label) {
      $fontSelect.append($('<option>').val(key).text(label));
    });

    var $social = $('#mte-social');
    $.each(cfg.socialNetworks, function (key, label) {
      $social.append(
        '<div class="input-group mte-social-row"><span class="input-group-addon">' + esc(label) + '</span>' +
        '<input type="url" class="form-control" data-mte-social="' + esc(key) + '" placeholder="https://"></div>'
      );
    });

    langOptions($('#mte-footer-lang'), state.footerLang);
    langOptions($('#mte-preview-lang'), state.previewLang);
    $('[data-mte-global="mode"]').val(String(cfg.mode));
    $('[data-mte-global="skip"]').val(cfg.skip);
    $('.mte-no-logo').toggleClass('hidden', !!cfg.hasLogo);

    function fillDesignForm() {
      var s = state.settings[state.layout];
      $('input[name="mte_layout"][value="' + state.layout + '"]').prop('checked', true);
      $('.mte-layout-card').removeClass('active').filter('[data-layout="' + state.layout + '"]').addClass('active');
      $('[data-mte-setting]').each(function () {
        var key = $(this).data('mte-setting');
        if (this.type === 'checkbox') {
          this.checked = !!parseInt(s[key], 10);
        } else {
          $(this).val(s[key]);
        }
      });
      $('[data-mte-social]').each(function () {
        $(this).val(state.social[$(this).data('mte-social')] || '');
      });
      fillMultilang();
    }

    function fillMultilang() {
      $('[data-mte-ml]').each(function () {
        var field = $(this).data('mte-ml');
        var values = state.multilang[field] || {};
        $(this).val(values[state.footerLang] || '');
      });
    }

    function readDesignForm() {
      var s = state.settings[state.layout];
      $('[data-mte-setting]').each(function () {
        var key = $(this).data('mte-setting');
        s[key] = this.type === 'checkbox' ? (this.checked ? 1 : 0) : (this.type === 'number' ? parseFloat(this.value) : this.value);
      });
      $('[data-mte-social]').each(function () {
        var v = $.trim(this.value);
        if (v) {
          state.social[$(this).data('mte-social')] = v;
        } else {
          delete state.social[$(this).data('mte-social')];
        }
      });
      $('[data-mte-ml]').each(function () {
        var field = $(this).data('mte-ml');
        state.multilang[field] = state.multilang[field] || {};
        state.multilang[field][state.footerLang] = this.value;
      });
    }

    function designPayload(idLang) {
      readDesignForm();
      return {
        layout: state.layout,
        settings: JSON.stringify(state.settings[state.layout]),
        social: JSON.stringify(state.social),
        multilang: b64(JSON.stringify(state.multilang)),
        id_lang: idLang
      };
    }

    var refreshDesignPreview = debounce(function () {
      var data = designPayload(state.previewLang);
      data.content = '';
      api('preview', data).then(function (res) {
        writeFrame(document.getElementById('mte-design-preview'), res.html);
      });
    }, 350);

    $('.mte-layout-card').on('click', function () {
      readDesignForm();
      state.layout = $(this).data('layout');
      fillDesignForm();
      refreshDesignPreview();
    });
    $('#mte-tab-design').on('input change', '[data-mte-setting], [data-mte-social], [data-mte-ml]', refreshDesignPreview);
    $('#mte-footer-lang').on('change', function () {
      readDesignForm();
      state.footerLang = parseInt(this.value, 10);
      fillMultilang();
    });
    $('#mte-preview-lang').on('change', function () {
      state.previewLang = parseInt(this.value, 10);
      refreshDesignPreview();
    });
    $('[data-mte-device]').on('click', function () {
      $('[data-mte-device]').removeClass('active');
      $(this).addClass('active');
      $('#mte-tab-design .mte-frame-wrap').toggleClass('mte-mobile', $(this).data('mte-device') === 'mobile');
    });

    $('#mte-reset-design').on('click', function () {
      state.settings[state.layout] = $.extend({}, cfg.defaults[state.layout]);
      fillDesignForm();
      refreshDesignPreview();
    });

    $('#mte-save-design').on('click', function () {
      var data = designPayload(state.previewLang);
      data.mode = $('[data-mte-global="mode"]').val();
      data.skip = $('[data-mte-global="skip"]').val();
      api('saveDesign', data).then(function () { toast(t.saved); });
    });

    fillDesignForm();
    refreshDesignPreview();

    /* ---------------- CONTENT TAB ---------------- */

    langOptions($('#mte-content-lang'), state.contentLang);
    $('#mte-test-email').val(cfg.employeeEmail);

    function initEditor() {
      if (!cfg.tinymce || typeof window.tinymce === 'undefined') {
        state.sourceMode = true;
        $('#mte-toggle-source').hide();
        return $.Deferred().resolve().promise();
      }
      var d = $.Deferred();
      var s = state.settings[state.layout];
      window.tinymce.init({
        selector: '#mte-editor',
        height: 480,
        menubar: false,
        branding: false,
        skin: cfg.tinymceSkin,
        plugins: 'code table link image lists textcolor colorpicker',
        toolbar: 'formatselect | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image table | removeformat code',
        // Keep characters as they are: no &alpha; / &#945; conversions (the Greek bug of the core editor).
        entity_encoding: 'raw',
        // Keep the HTML as written (custom styles, tables, attributes).
        verify_html: false,
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+body[style],+a[div|p|table|h1|h2|h3|span|img]',
        convert_urls: false,
        relative_urls: false,
        remove_script_host: false,
        forced_root_block: 'p',
        content_style: 'body{font-family:Arial,sans-serif;font-size:' + (s.font_size || 14) + 'px;padding:10px;} p,td,th,li,span,div{font-size:inherit;} .mte-button{display:inline-block;padding:10px 22px;background:' + s.button_bg + ';color:' + s.button_text + ';border-radius:4px;text-decoration:none;} .mte-box{border:1px solid #ddd;padding:12px;margin:0 0 12px;}',
        setup: function (ed) {
          state.editor = ed;
          ed.on('change keyup', function () { state.dirty = true; });
          ed.on('init', function () { d.resolve(); });
        }
      });
      setTimeout(function () { d.resolve(); }, 4000);
      return d.promise();
    }

    function getContent() {
      if (state.editor && !state.sourceMode) {
        return state.editor.getContent();
      }
      return $('#mte-editor').val();
    }

    function setContent(html) {
      $('#mte-editor').val(html);
      if (state.editor && !state.sourceMode) {
        state.editor.setContent(html);
      }
    }

    function insertHtml(html) {
      if (state.editor && !state.sourceMode) {
        state.editor.insertContent(html);
      } else {
        var el = document.getElementById('mte-editor');
        var start = el.selectionStart || 0;
        el.value = el.value.substr(0, start) + html + el.value.substr(el.selectionEnd || start);
        el.selectionStart = el.selectionEnd = start + html.length;
        el.focus();
      }
      state.dirty = true;
    }

    $('#mte-toggle-source').on('click', function () {
      if (!state.editor) {
        return;
      }
      if (state.sourceMode) {
        state.editor.show();
        state.editor.setContent($('#mte-editor').val());
        state.sourceMode = false;
        $(this).find('span').text(t.source);
      } else {
        $('#mte-editor').val(state.editor.getContent());
        state.editor.hide();
        state.sourceMode = true;
        $(this).find('span').text(t.visual);
      }
    });
    $('#mte-editor').on('input', function () { state.dirty = true; });

    function loadTemplates() {
      api('listTemplates', { id_lang: state.contentLang }).then(function (res) {
        state.templates = res.templates;
        renderTemplateList();
      });
    }

    function renderTemplateList() {
      var q = $.trim($('#mte-template-search').val()).toLowerCase();
      var $list = $('#mte-template-list').empty();
      var group = null;
      state.templates.forEach(function (tpl) {
        if (state.filter === 'custom' && !tpl.custom) {
          return;
        }
        if (q && tpl.key.toLowerCase().indexOf(q) === -1) {
          return;
        }
        if (tpl.module !== group) {
          group = tpl.module;
          $list.append('<div class="mte-group">' + esc(group === 'core' ? 'PrestaShop' : group) + '</div>');
        }
        var badges = '';
        if (tpl.custom) {
          badges += '<span class="label ' + (tpl.active ? 'label-success' : 'label-default') + '">' + esc(tpl.active ? t.customised : t.disabled) + '</span>';
        }
        if (!tpl.translated) {
          badges += '<span class="label label-warning" title="' + esc(t.notTranslated) + '">!</span>';
        }
        var $item = $('<a href="#" class="mte-item">').attr('data-key', tpl.key).html(esc(tpl.template) + ' ' + badges);
        if (state.current && state.current.key === tpl.key) {
          $item.addClass('active');
        }
        $list.append($item);
      });
    }

    $('#mte-template-search').on('input', renderTemplateList);
    $('[data-mte-filter]').on('click', function (e) {
      e.preventDefault();
      $('[data-mte-filter]').removeClass('active');
      $(this).addClass('active');
      state.filter = $(this).data('mte-filter');
      renderTemplateList();
    });
    $('#mte-content-lang').on('change', function () {
      if (state.dirty && !window.confirm(t.confirmLeave)) {
        $(this).val(state.contentLang);
        return;
      }
      state.contentLang = parseInt(this.value, 10);
      state.dirty = false;
      if (state.current) {
        openTemplate(state.current);
      }
      loadTemplates();
    });

    function tplParams() {
      return { module_key: state.current.module, template: state.current.template, id_lang: state.contentLang };
    }

    function openTemplate(tpl) {
      state.current = tpl;
      $('#mte-template-list .mte-item').removeClass('active').filter('[data-key="' + tpl.key + '"]').addClass('active');
      api('loadTemplate', tplParams()).then(function (res) {
        $('#mte-editor-body').removeClass('hidden');
        $('#mte-editing-title').text((tpl.module === 'core' ? '' : tpl.module + ' / ') + tpl.template);
        $('#mte-editing-file').text(res.file ? ' — ' + res.file : '');
        $('#mte-subject').val(res.subject);
        $('#mte-active').prop('checked', res.active);
        $('#mte-delete-template').toggle(res.custom);
        $('#mte-content-preview-wrap').addClass('hidden');
        var $vars = $('#mte-vars').empty();
        res.variables.forEach(function (v) {
          $vars.append($('<a href="#" class="mte-var">').text(v));
        });
        setContent(res.content);
        state.dirty = false;
        if (!res.custom) {
          importOriginal(false);
        }
      });
    }

    $('#mte-template-list').on('click', '.mte-item', function (e) {
      e.preventDefault();
      if (state.dirty && !window.confirm(t.confirmLeave)) {
        return;
      }
      var key = $(this).data('key');
      var tpl = state.templates.filter(function (x) { return x.key === key; })[0];
      if (tpl) {
        openTemplate(tpl);
      }
    });

    $('#mte-vars').on('click', '.mte-var', function (e) {
      e.preventDefault();
      insertHtml($(this).text());
    });

    function importOriginal(ask) {
      if (ask && $.trim(getContent()) !== '' && !window.confirm(t.confirmImport)) {
        return;
      }
      var data = tplParams();
      data.normalize = $('#mte-normalize').is(':checked') ? 1 : 0;
      api('importOriginal', data).then(function (res) {
        setContent(res.content);
        state.dirty = !!ask;
      });
    }
    $('#mte-import').on('click', function () { importOriginal(true); });

    $('#mte-insert-button').on('click', function () {
      var text = window.prompt(t.buttonText, '');
      if (!text) {
        return;
      }
      var url = window.prompt(t.buttonUrl, '{shop_url}') || '{shop_url}';
      insertHtml('<p style="text-align:center"><a class="mte-button" href="' + esc(url) + '">' + esc(text) + '</a></p>');
    });
    $('#mte-insert-box').on('click', function () {
      insertHtml('<div class="mte-box"><p>...</p></div>');
    });

    $('#mte-repair').on('click', function () {
      var before = getContent();
      api('repair', { content: b64(before) }).then(function (res) {
        if (res.changed) {
          setContent(res.content);
          state.dirty = true;
          toast(t.repaired);
        } else {
          toast(t.nothingToRepair);
        }
      });
    });

    $('#mte-save-template').on('click', function () {
      if (!state.current) {
        return;
      }
      var data = tplParams();
      data.content = b64(getContent());
      data.subject = $('#mte-subject').val();
      data.active = $('#mte-active').is(':checked') ? 1 : 0;
      api('saveTemplate', data).then(function () {
        state.dirty = false;
        toast(t.saved);
        $('#mte-delete-template').show();
        loadTemplates();
      });
    });

    $('#mte-preview-template').on('click', function () {
      if (!state.current) {
        return;
      }
      api('preview', { content: b64(getContent()), id_lang: state.contentLang, subject: $('#mte-subject').val() }).then(function (res) {
        $('#mte-content-preview-wrap').removeClass('hidden');
        writeFrame(document.getElementById('mte-content-preview'), res.html);
      });
    });

    $('#mte-send-test').on('click', function () {
      if (!state.current) {
        return;
      }
      var data = tplParams();
      data.email = $('#mte-test-email').val();
      api('sendTest', data).then(function () { toast(t.sent); });
    });

    $('#mte-delete-template').on('click', function () {
      if (!state.current || !window.confirm(t.confirmDelete)) {
        return;
      }
      api('deleteTemplate', tplParams()).then(function () {
        toast(t.saved);
        loadTemplates();
        openTemplate(state.current);
      });
    });

    $('#mte-bulk-import').on('click', function () {
      if (!window.confirm(t.confirmBulk)) {
        return;
      }
      var $btn = $(this).prop('disabled', true);
      api('bulkImport', { id_lang: state.contentLang }).then(function (res) {
        toast(t.imported + ': ' + res.count);
        loadTemplates();
      }).always(function () { $btn.prop('disabled', false); });
    });

    window.addEventListener('beforeunload', function (e) {
      if (state.dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    var contentInit = false;
    $('a[href="#mte-tab-content"]').on('shown.bs.tab click', function () {
      if (contentInit) {
        return;
      }
      contentInit = true;
      initEditor().then(loadTemplates);
    });

    /* ---------------- FILES TAB ---------------- */

    langOptions($('#mte-files-lang'), state.filesLang);

    function loadFiles() {
      api('listFiles', { id_lang: state.filesLang }).then(function (res) {
        state.files = res.files;
        renderFiles();
      });
    }

    function renderFiles() {
      var q = $.trim($('#mte-file-search').val()).toLowerCase();
      var $list = $('#mte-file-list').empty();
      var group = null;
      state.files.forEach(function (f) {
        if (q && f.path.toLowerCase().indexOf(q) === -1) {
          return;
        }
        if (f.module !== group) {
          group = f.module;
          $list.append('<div class="mte-group">' + esc(group === 'core' ? 'PrestaShop' : group) + '</div>');
        }
        var name = f.path.split('/').pop();
        var $a = $('<a href="#" class="mte-item">').attr('data-path', f.path).attr('title', f.path).text(name);
        if (state.currentFile === f.path) {
          $a.addClass('active');
        }
        $list.append($a);
      });
    }

    function renderBackups(list) {
      var $b = $('#mte-backups').empty();
      if (!list.length) {
        $b.append('<tr><td class="text-muted">' + esc(t.noBackups) + '</td></tr>');
        return;
      }
      list.forEach(function (b) {
        $b.append('<tr><td>' + esc(b.date) + '</td><td>' + esc(Math.round(b.size / 102.4) / 10) + ' KB</td><td class="text-right"><button type="button" class="btn btn-default btn-xs" data-mte-restore="' + esc(b.name) + '">' + esc(t.restore) + '</button></td></tr>');
      });
    }

    $('#mte-file-search').on('input', renderFiles);
    $('#mte-files-lang').on('change', function () {
      state.filesLang = parseInt(this.value, 10);
      loadFiles();
    });

    $('#mte-file-list').on('click', '.mte-item', function (e) {
      e.preventDefault();
      var path = $(this).data('path');
      api('loadFile', { path: path }).then(function (res) {
        state.currentFile = path;
        renderFiles();
        $('#mte-file-body').removeClass('hidden');
        $('#mte-file-title').text(path);
        $('#mte-file-encoding').text(t.encoding + ': ' + res.encoding);
        $('#mte-file-editor').val(res.content);
        $('#mte-file-preview-wrap').addClass('hidden');
        var warn = [];
        if (res.encoding !== 'UTF-8') {
          warn.push(t.converted);
        }
        $('#mte-file-warning').toggleClass('hidden', !warn.length).text(warn.join(' '));
        $('#mte-save-file').prop('disabled', !res.writable);
        renderBackups(res.backups);
      });
    });

    $('#mte-save-file').on('click', function () {
      if (!state.currentFile) {
        return;
      }
      api('saveFile', { path: state.currentFile, content: b64($('#mte-file-editor').val()) }).then(function (res) {
        toast(t.saved);
        renderBackups(res.backups);
      });
    });

    $('#mte-repair-file').on('click', function () {
      api('repair', { content: b64($('#mte-file-editor').val()) }).then(function (res) {
        if (res.changed) {
          $('#mte-file-editor').val(res.content);
          toast(t.repaired);
        } else {
          toast(t.nothingToRepair);
        }
      });
    });

    $('#mte-preview-file').on('click', function () {
      $('#mte-file-preview-wrap').removeClass('hidden');
      var content = $('#mte-file-editor').val();
      if (!/\.html?$/i.test(state.currentFile || '')) {
        content = '<pre style="white-space:pre-wrap;font-family:monospace">' + esc(content) + '</pre>';
      }
      writeFrame(document.getElementById('mte-file-preview'), content);
    });

    $('#mte-backups').on('click', '[data-mte-restore]', function () {
      if (!window.confirm(t.confirmRestore)) {
        return;
      }
      api('restoreBackup', { path: state.currentFile, backup: $(this).data('mte-restore') }).then(function (res) {
        $('#mte-file-editor').val(res.content);
        renderBackups(res.backups);
        toast(t.saved);
      });
    });

    var filesInit = false;
    $('a[href="#mte-tab-files"]').on('shown.bs.tab click', function () {
      if (!filesInit) {
        filesInit = true;
        loadFiles();
      }
    });

    if (!cfg.canEdit) {
      $('#mte-save-design, #mte-save-template, #mte-save-file, #mte-delete-template, #mte-bulk-import').prop('disabled', true);
    }
  });
})(jQuery);
