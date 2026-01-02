/**
 * AI Search Schema - Setup Wizard JavaScript
 *
 * @package Aivec\AiSearchSchema
 */

/* global jQuery, wp, aisWizardData */

(function ($) {
  'use strict';

  const Wizard = {
    /**
     * Initialize wizard.
     */
    init: function () {
      this.bindEvents();
      this.initTypeSelection();
      this.initHoursTable();
      this.initImportModal();
      this.initLogoUpload();
      this.initGeocoding();
      this.initSchemaPreview();
    },

    /**
     * Bind global events.
     */
    bindEvents: function () {
      // Next button click
      $(document).on('click', '.ais-wizard-next-btn', this.handleNextClick.bind(this));

      // Language switcher
      $(document).on('click', '.ais-wizard-lang-btn', this.handleLanguageSwitch.bind(this));
    },

    /**
     * Handle next button click.
     *
     * @param {Event} e Click event.
     */
    handleNextClick: function (e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const nextStep = $btn.data('next');

      if (!nextStep) {
        return;
      }

      // Validate current step
      if (!this.validateCurrentStep()) {
        return;
      }

      // Collect form data
      const formData = this.collectFormData();

      // Save progress via AJAX
      this.saveProgress(formData, nextStep);
    },

    /**
     * Validate current step.
     *
     * @return {boolean} True if valid.
     */
    validateCurrentStep: function () {
      let isValid = true;
      const $step = $('.ais-wizard-step');

      // Check required fields
      $step.find('input[required], select[required], textarea[required]').each(function () {
        const $field = $(this);
        if (!$field.val()) {
          isValid = false;
          $field.addClass('ais-wizard-form__input--error');
          $field.one('input change', function () {
            $(this).removeClass('ais-wizard-form__input--error');
          });
        }
      });

      // Check type selection (step 2)
      if ($step.hasClass('ais-wizard-step--type')) {
        const selectedType = $('input[name="entity_type"]:checked').val();
        if (!selectedType) {
          isValid = false;
          $('.ais-wizard-type-grid').addClass('ais-wizard-type-grid--error');
        }
      }

      return isValid;
    },

    /**
     * Collect form data from current step.
     *
     * @return {Object} Form data.
     */
    collectFormData: function () {
      const data = {};

      $('.ais-wizard-step').find('input, select, textarea').each(function () {
        const $field = $(this);
        const name = $field.attr('name');

        if (!name) {
          return;
        }

        if ($field.is(':checkbox')) {
          data[name] = $field.is(':checked') ? '1' : '0';
        } else if ($field.is(':radio')) {
          if ($field.is(':checked')) {
            data[name] = $field.val();
          }
        } else {
          data[name] = $field.val();
        }
      });

      return data;
    },

    /**
     * Save progress via AJAX.
     *
     * @param {Object} formData Form data.
     * @param {string} nextStep Next step slug.
     */
    saveProgress: function (formData, nextStep) {
      const $btn = $('.ais-wizard-next-btn');
      $btn.prop('disabled', true);

      $.ajax({
        url: aisWizardData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'ai_search_schema_wizard_save_step',
          nonce: aisWizardData.nonce,
          step: aisWizardData.currentStep,
          data: formData
        },
        success: function (response) {
          if (response.success) {
            // Navigate to next step
            window.location.href = aisWizardData.wizardUrl + '&step=' + nextStep;
          } else {
            alert(response.data.message || aisWizardData.strings.errorSaving);
            $btn.prop('disabled', false);
          }
        },
        error: function () {
          alert(aisWizardData.strings.errorSaving);
          $btn.prop('disabled', false);
        }
      });
    },

    /**
     * Handle language switch.
     *
     * @param {Event} e Click event.
     */
    handleLanguageSwitch: function (e) {
      e.preventDefault();

      const $btn = $(e.currentTarget);
      const locale = $btn.data('locale');

      if (!locale) {
        return;
      }

      // Update URL with locale parameter
      const url = new URL(window.location.href);
      url.searchParams.set('locale', locale);
      window.location.href = url.toString();
    },

    /**
     * Initialize type selection (step 2).
     */
    initTypeSelection: function () {
      const $typeGrid = $('.ais-wizard-type-grid');
      if (!$typeGrid.length) {
        return;
      }

      const $nextBtn = $('#ais-wizard-type-next');

      // Type card click
      $typeGrid.on('click', '.ais-wizard-type-card', function () {
        const $card = $(this);
        const $radio = $card.find('input[type="radio"]');

        // Update selection
        $('.ais-wizard-type-card').removeClass('ais-wizard-type-card--selected');
        $card.addClass('ais-wizard-type-card--selected');
        $radio.prop('checked', true);

        // Enable next button
        $nextBtn.prop('disabled', false);

        // Update info box
        Wizard.updateTypeInfo($radio.val());
      });

      // Check initial selection
      const $selectedType = $('input[name="entity_type"]:checked');
      if ($selectedType.length) {
        $nextBtn.prop('disabled', false);
        this.updateTypeInfo($selectedType.val());
      }
    },

    /**
     * Update type info box.
     *
     * @param {string} type Selected type.
     */
    updateTypeInfo: function (type) {
      const $infoBox = $('#ais-wizard-type-info');
      const $title = $('#ais-wizard-type-info-title');
      const $text = $('#ais-wizard-type-info-text');

      const typeInfo = {
        LocalBusiness: {
          title: aisWizardData.strings.localBusinessTitle || 'Local Business Selected',
          text: aisWizardData.strings.localBusinessText || 'You will be able to enter your business address, hours, and contact information.'
        },
        Organization: {
          title: aisWizardData.strings.organizationTitle || 'Organization Selected',
          text: aisWizardData.strings.organizationText || 'Perfect for companies, non-profits, and institutions.'
        },
        Person: {
          title: aisWizardData.strings.personTitle || 'Personal Site Selected',
          text: aisWizardData.strings.personText || 'Great for blogs, portfolios, and freelancer websites.'
        },
        WebSite: {
          title: aisWizardData.strings.websiteTitle || 'Online Service Selected',
          text: aisWizardData.strings.websiteText || 'Ideal for news sites, web apps, and online tools.'
        }
      };

      const info = typeInfo[type];
      if (info) {
        $title.text(info.title);
        $text.text(info.text);
        $infoBox.slideDown(200);
      } else {
        $infoBox.slideUp(200);
      }
    },

    /**
     * Initialize hours table (step 4).
     */
    initHoursTable: function () {
      const $hoursTable = $('.ais-wizard-hours-table');
      if (!$hoursTable.length) {
        return;
      }

      // Toggle day open/closed
      $hoursTable.on('change', '.ais-wizard-hours-toggle', function () {
        const $toggle = $(this);
        const $row = $toggle.closest('.ais-wizard-hours-table__row');
        const $selects = $row.find('.ais-wizard-hours-select');

        if ($toggle.is(':checked')) {
          $selects.prop('disabled', false);
          // Set default hours if empty
          $selects.each(function () {
            const $select = $(this);
            if (!$select.val()) {
              const name = $select.attr('name');
              if (name && name.includes('_opens')) {
                $select.val('09:00');
              } else if (name && name.includes('_closes')) {
                $select.val('18:00');
              }
            }
          });
        } else {
          $selects.prop('disabled', true);
        }

        Wizard.updateHoursPreview();
      });

      // Time select change
      $hoursTable.on('change', '.ais-wizard-hours-select', function () {
        Wizard.updateHoursPreview();
      });

      // Quick setup: Same hours every weekday
      $('#ais-wizard-hours-weekdays').on('click', function () {
        Wizard.setHoursForDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '09:00', '18:00');
      });

      // Quick setup: Same hours every day
      $('#ais-wizard-hours-everyday').on('click', function () {
        Wizard.setHoursForDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], '09:00', '18:00');
      });

      // Quick setup: Clear all
      $('#ais-wizard-hours-clear').on('click', function () {
        $('.ais-wizard-hours-table__row').each(function () {
          const $row = $(this);
          $row.find('.ais-wizard-hours-toggle').prop('checked', false);
          $row.find('.ais-wizard-hours-select').prop('disabled', true).val('');
        });
        Wizard.updateHoursPreview();
      });

      // Initial preview
      this.updateHoursPreview();
    },

    /**
     * Set hours for specific days.
     *
     * @param {Array}  days   Array of day keys.
     * @param {string} opens  Opening time.
     * @param {string} closes Closing time.
     */
    setHoursForDays: function (days, opens, closes) {
      days.forEach(function (day) {
        const $row = $(`.ais-wizard-hours-table__row[data-day="${day}"]`);
        if ($row.length) {
          $row.find('.ais-wizard-hours-toggle').prop('checked', true);
          $row.find('.ais-wizard-hours-select').prop('disabled', false);
          $row.find(`select[name="hours_${day}_opens"]`).val(opens);
          $row.find(`select[name="hours_${day}_closes"]`).val(closes);
        }
      });
      this.updateHoursPreview();
    },

    /**
     * Update hours preview.
     */
    updateHoursPreview: function () {
      const $preview = $('#ais-wizard-hours-preview');
      const days = [];
      const dayNames = {
        monday: aisWizardData.strings.monday || 'Monday',
        tuesday: aisWizardData.strings.tuesday || 'Tuesday',
        wednesday: aisWizardData.strings.wednesday || 'Wednesday',
        thursday: aisWizardData.strings.thursday || 'Thursday',
        friday: aisWizardData.strings.friday || 'Friday',
        saturday: aisWizardData.strings.saturday || 'Saturday',
        sunday: aisWizardData.strings.sunday || 'Sunday'
      };

      $('.ais-wizard-hours-table__row').each(function () {
        const $row = $(this);
        const day = $row.data('day');
        const isOpen = $row.find('.ais-wizard-hours-toggle').is(':checked');

        if (isOpen) {
          const opens = $row.find(`select[name="hours_${day}_opens"]`).val();
          const closes = $row.find(`select[name="hours_${day}_closes"]`).val();
          if (opens && closes) {
            days.push({
              name: dayNames[day] || day,
              hours: `${opens} - ${closes}`
            });
          }
        }
      });

      if (days.length === 0) {
        $preview.html('<p class="ais-wizard-hours-preview__empty">' + (aisWizardData.strings.noHoursSet || 'Set your business hours above to see a preview.') + '</p>');
      } else {
        let html = '<ul class="ais-wizard-hours-preview__list">';
        days.forEach(function (day) {
          html += `<li><strong>${day.name}:</strong> ${day.hours}</li>`;
        });
        html += '</ul>';
        $preview.html(html);
      }
    },

    /**
     * Initialize import modal.
     */
    initImportModal: function () {
      const $modal = $('#ais-wizard-import-modal');
      if (!$modal.length) {
        return;
      }

      // Show modal
      $('#ais-wizard-show-import').on('click', function () {
        $modal.fadeIn(200);
      });

      // Close modal
      $modal.on('click', '.ais-wizard-modal__close, .ais-wizard-modal__backdrop', function () {
        $modal.fadeOut(200);
      });

      // Import button
      $modal.on('click', '.ais-wizard-import-btn', function () {
        const $btn = $(this);
        const source = $btn.data('source');

        if (!source) {
          return;
        }

        $btn.prop('disabled', true).text(aisWizardData.strings.importing || 'Importing...');

        $.ajax({
          url: aisWizardData.ajaxUrl,
          method: 'POST',
          data: {
            action: 'ai_search_schema_wizard_import',
            nonce: aisWizardData.nonce,
            source: source
          },
          success: function (response) {
            if (response.success) {
              $btn.text(aisWizardData.strings.imported || 'Imported!');
              setTimeout(function () {
                window.location.href = aisWizardData.wizardUrl + '&step=basics';
              }, 1000);
            } else {
              alert(response.data.message || aisWizardData.strings.importError || 'Import failed.');
              $btn.prop('disabled', false).text(aisWizardData.strings.import || 'Import');
            }
          },
          error: function () {
            alert(aisWizardData.strings.importError || 'Import failed.');
            $btn.prop('disabled', false).text(aisWizardData.strings.import || 'Import');
          }
        });
      });
    },

    /**
     * Initialize logo upload.
     */
    initLogoUpload: function () {
      const $uploadBtn = $('#ais-wizard-upload-logo');
      const $removeBtn = $('#ais-wizard-remove-logo');
      const $preview = $('#ais-wizard-logo-preview');
      const $input = $('#ais-wizard-logo-url');

      if (!$uploadBtn.length) {
        return;
      }

      // Upload button click
      $uploadBtn.on('click', function () {
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
          alert('WordPress media library is not available.');
          return;
        }

        const frame = wp.media({
          title: aisWizardData.strings.selectLogo || 'Select Logo',
          button: {
            text: aisWizardData.strings.useLogo || 'Use this logo'
          },
          multiple: false,
          library: {
            type: 'image'
          }
        });

        frame.on('select', function () {
          const attachment = frame.state().get('selection').first().toJSON();
          $input.val(attachment.url);
          $preview.html('<img src="' + attachment.url + '" alt="Logo preview">');
          $removeBtn.show();
        });

        frame.open();
      });

      // Remove button click
      $removeBtn.on('click', function () {
        $input.val('');
        $preview.html('<div class="ais-wizard-logo-upload__placeholder"><svg viewBox="0 0 24 24" width="48" height="48" fill="#9ca3af"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg><span>' + (aisWizardData.strings.noLogo || 'No logo selected') + '</span></div>');
        $removeBtn.hide();
      });
    },

    /**
     * Initialize geocoding.
     */
    initGeocoding: function () {
      const $geocodeBtn = $('#ais-wizard-geocode-btn');
      if (!$geocodeBtn.length) {
        return;
      }

      $geocodeBtn.on('click', function () {
        const postalCode = $('#ais-wizard-postal-code').val();
        const region = $('#ais-wizard-region').val();
        const locality = $('#ais-wizard-locality').val();
        const street = $('#ais-wizard-street').val();

        // Check if at least one address field is filled
        if (!postalCode && !region && !locality && !street) {
          alert(aisWizardData.strings.enterAddress || 'Please enter an address first.');
          return;
        }

        $geocodeBtn.prop('disabled', true).text(aisWizardData.strings.fetching || 'Fetching...');

        $.ajax({
          url: aisWizardData.ajaxUrl,
          method: 'POST',
          data: {
            action: 'ai_search_schema_geocode',
            nonce: aisWizardData.geocodeNonce || aisWizardData.nonce,
            'address[postal_code]': postalCode,
            'address[region]': region,
            'address[locality]': locality,
            'address[street_address]': street
          },
          success: function (response) {
            if (response.success && response.data) {
              $('#ais-wizard-lat').val(response.data.lat);
              $('#ais-wizard-lng').val(response.data.lng);
            } else {
              alert(response.data.message || aisWizardData.strings.geocodeError || 'Could not fetch coordinates.');
            }
            $geocodeBtn.prop('disabled', false).html('<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>' + (aisWizardData.strings.getCoordinates || 'Get Coordinates'));
          },
          error: function () {
            alert(aisWizardData.strings.geocodeError || 'Could not fetch coordinates.');
            $geocodeBtn.prop('disabled', false).html('<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3c-.46-4.17-3.77-7.48-7.94-7.94V1h-2v2.06C6.83 3.52 3.52 6.83 3.06 11H1v2h2.06c.46 4.17 3.77 7.48 7.94 7.94V23h2v-2.06c4.17-.46 7.48-3.77 7.94-7.94H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>' + (aisWizardData.strings.getCoordinates || 'Get Coordinates'));
          }
        });
      });
    },

    /**
     * Initialize schema preview (complete step).
     */
    initSchemaPreview: function () {
      const $showBtn = $('#ais-wizard-show-schema');
      const $code = $('#ais-wizard-schema-code');

      if (!$showBtn.length) {
        return;
      }

      $showBtn.on('click', function () {
        if ($code.is(':visible')) {
          $code.slideUp(200);
          $showBtn.text(aisWizardData.strings.viewSchema || 'View JSON-LD Schema');
        } else {
          // Fetch schema
          $.ajax({
            url: aisWizardData.ajaxUrl,
            method: 'POST',
            data: {
              action: 'ai_search_schema_wizard_get_schema',
              nonce: aisWizardData.nonce
            },
            success: function (response) {
              if (response.success && response.data.schema) {
                $code.text(JSON.stringify(response.data.schema, null, 2)).slideDown(200);
                $showBtn.text(aisWizardData.strings.hideSchema || 'Hide JSON-LD Schema');
              } else {
                $code.text(aisWizardData.strings.noSchema || 'No schema generated yet.').slideDown(200);
              }
            },
            error: function () {
              $code.text(aisWizardData.strings.schemaError || 'Could not load schema.').slideDown(200);
            }
          });
        }
      });
    }
  };

  // Initialize on document ready
  $(document).ready(function () {
    Wizard.init();
  });
})(jQuery);
