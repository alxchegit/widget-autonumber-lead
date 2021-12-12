define(['jquery', 
		'lib/components/base/modal',
		"https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js", 
		'intl-tel-input', 
		'intl-tel-input-utils'
	], function ($, Modal, Select2) {

    var CustomWidget = function(){
        var self   = this,
			system = self.system(),
			ai_widget_name = 'Виджет автонумерации сделки',
			ai_widget_code = 'autonumber_lead',
			urlAPI = 'https://amoai.ru/ws/autonumber_lead',
			error_msg = 'Произошла ошибка на стороне сервера.';

		// маска для чисел
		const maskTemplates = [
			{
				value :`{{000000001}}`,
				text: '9-ти значное число с 0 в начале'
			},
			{
				value :`{{000001}}`,
				text: '6-ти значное число с 0 в начале'
			},
			{
				value :`{{1}}`,
				text: 'Число без 0 в начале'
			},
			{
				value :`{#1#}`,
				text: 'Число с которого начинается подсчет'
			}
		];
		var _fields = [];


		// Открытие модального окна
		this.openModal = function (data, class_name) {
			modal = new Modal({
				class_name: 'modal-list ' + class_name,
				init: function ($modal_body) {
					var $this = $(this);
					$modal_body
					.trigger('modal:loaded')
					.html(data)
					.trigger('modal:centrify')
					.append('<span class="modal-body__close"><span class="icon icon-modal-close"></span></span>');
				},
				destroy: function () {}
			});
		};

		// Показ уведомлений
		this.openAlert = function (text, type) {
			if (!type) type = 'success';

			if (type == 'success') {
				return new Modal()._showSuccess(text, false, 3000);
			} else if (type == 'error') {
				return new Modal()._showError(text, false);
			} else {
				return false;
			}
		};

		this.openNotifications = function (text, type) {
			var params = {
				header: ai_widget_name,
				text:text
			};

			if (!type) type = 'success';

			if (type == 'success') {
				AMOCRM.notifications.show_message(params);
			} else if (type == 'error') {
				AMOCRM.notifications.show_message_error(params);
			} else {
				return false;
			}
		};

		// Генерация шаблона
		this.getTemplate = function (template, callback) {
			template = template || '';

			return self.render({
				href: '/templates/' + template + '.twig',
				base_path: self.base_path,
				load: callback
			});
		};

		// Добавление css
		this.appendCss = function (file) {
			if ($('link[href="' + self.base_path + file + '?v=' + self.params.version + '"]').length) {
				return false;
			}

			$('head').append('<link type="text/css" rel="stylesheet" href="' + self.base_path + file + '?v=' + self.params.version + '">');

			return true;
		};

		// Получение id виджета
		this.id = function (postfix, hash) {
			hash    = typeof hash !== 'undefined' ? hash : true;
			postfix = typeof postfix !== 'undefined' ? postfix : '';

			return (hash ? '#' : '') + self.params.widget_code + (postfix?'_' + postfix:'');
		};

		// Загрузка
		this.dp_loader = function (show) {
			show = typeof show !== 'undefined' ? show : true;

			if (show) {
				if (!$(self.id()).find('.amoai-autonumber_lead-dp_settings_container .overlay-loader').length) {
					$(self.id()).find('.amoai-autonumber_lead-dp_settings_container').append(
						'<div class="settings-loader-wrapper">\
		               		 <span class="pipeline_leads__load_more__spinner spinner-icon spinner-icon-abs-center settings-spinner"></span>\
		               	</div>'
					);
				}
			} else {
				$(self.id()).find('.amoai-autonumber_lead-dp_settings_container .settings-loader-wrapper').remove();
			}
		};

		// Установка виджета
		this.installWidget = function(btn) {
			return new Promise(function(succeed, fail) {
				btn.trigger('button:load:start');

				$.ajax({
					url: urlAPI + '/install',
					type: 'post',
					data: {
						// Widget
						widget_code: ai_widget_code,

						// Account
						account_id:   AMOCRM.constant('account').id,
						license_date: AMOCRM.constant('account').paid_till,
						tariff: 	  AMOCRM.constant('account').tariffName,
						users:        self.activeUsers(),

						// User
						client_id:     AMOCRM.constant('user').id,
						name:          AMOCRM.constant('user').name,
						email: 	   	   AMOCRM.constant('user').login,
						profile_phone: AMOCRM.constant('user').personal_mobile,
						phone: $('.amoai-settings.' + self.params.widget_code).find('input[name=phone]').val()
					},
					dataType: 'json',
					success: function(data) {
						succeed(data);
					},
					error: function(jqxhr, status, errorMsg) {
						fail(new Error("Request failed: " + errorMsg));
					}
				});
			});
		};

		// Получение настроек виджета
		this.getWidgetSettings = function () {
			return new Promise(function (succeed, fail) {
				$.ajax({
					url: 'https://amoai.ru/pm/market-place/widget/' + ai_widget_code + '/settings',
					type: 'get',
					dataType: 'json',
					success: function (data) {
						succeed(data);
					},
					error: function (jqxhr, status, errorMsg) {
						fail(new Error("Request failed: " + errorMsg));
					}
				});
			});
		};

		// Получение статуса виджета
		this.getWidgetStatus = function() {
			return new Promise(function(succeed, fail) {
				$.ajax({
					url: urlAPI + '/status',
					type: 'post',
					data: {
						account_id: AMOCRM.constant('account').id,
						widget_code: ai_widget_code
					},
					dataType: 'json',
					success: function(data) {
						succeed(data);
					},
					error: function(jqxhr, status, errorMsg) {
						fail(new Error("Request failed: " + errorMsg));
					}
				});
			});
		};

		// Открыть форму "Купить"
		this.openOrderForm = function() {
			self.getTemplate('order_form', function(data) {
				var params = {
					btn_id: self.id('', false) + '_buy_widget_send',
					btn_text: 'Оставить заявку',
					name:  AMOCRM.constant('user').name,
					phone: AMOCRM.constant('user').personal_mobile,
					email: AMOCRM.constant('user').login,
					self: self
				};

				self.openModal(data.render(params), 'amoai-settings-order_form');
			});
		};

        // Отправка формы "Купить"
        this.sendOrderForm = function(btn) {
        	var form  = $('.amoai-settings-order_form form'),
				error = false;

			form.find('.required').each(function() {
				if($(this).val() == '') {
					$(this).addClass('error');

					error = true;
				} else {
					$(this).removeClass('error');
				}
			});

			if(error === false) {
				btn.trigger('button:load:start');

				$.ajax({
					url: urlAPI + '/order',
					type: 'post',
					data: {
						// Widget
						widget_code: ai_widget_code,

						// Account
						account_id:   AMOCRM.constant('account').id,
						license_date: AMOCRM.constant('account').paid_till,
						tariff: 	  AMOCRM.constant('account').tariffName,
						users:        self.activeUsers(),

						// User
						client_id: AMOCRM.constant('user').id,
						name:  	   form.find('input[name=name]').val(),
						phone: 	   form.find('input[name=phone]').val(),
						email:     form.find('input[name=email]').val(),
						comment:   form.find('textarea[name=comment]').val(),
						profile_phone: AMOCRM.constant('user').personal_mobile
					},
					dataType: 'json',
					success: function(res) {
						let type = res.error ? 'error' : 'success';
						self.openAlert(res.msg, type);
						btn.closest('.modal').remove();
					},
					error: function(){
						self.openAlert(error_msg, 'error');
						btn.trigger('button:load:stop');
					}
				});
			} else {
				self.openAlert('Заполните обязательные поля!', 'error');
			}
        };

        // Кол-во активных пользователей
		this.activeUsers = function () {
			let users = 0;

			for(var manager in AMOCRM.constant('managers')) {
				if(AMOCRM.constant('managers')[manager].active === true) {
					users++;
				}
			}

			return users;
		};

		// загрузка внешних стилей
		this.loadStyle = function (src, onload) {
			let link = document.createElement("link");
			link.type = 'text/css';
			link.rel = 'stylesheet';
			link.href = src;
			link.onload = onload;
			document.head.appendChild(link);
		};

		// Получение настроек
		this.getDpSettings = function() {
            self.dp_loader(true);
            // ID процесса
            let process_id = $(self.id()).find('input[name="params"]').val();
			// Пользовательские поля
			let accountCustomFields = Object.values(AMOCRM.constant('account').cf);			
			// Массив отобранных пользовательских полей сделки типа текст или число
			let anumFields = [];
			// классы для выделения полей по типу
			let field_class = {
				1:'amoai-text_field',
				2:'amoai-numeric_field'
			};
			
			for (cfKey in accountCustomFields){
				// Если кастомное поле сделки
				if (accountCustomFields[cfKey].ENTREE_DEALS == 1 && (accountCustomFields[cfKey].TYPE_ID == 2 || accountCustomFields[cfKey].TYPE_ID == 1 )){
					anumFields.push({
						id:accountCustomFields[cfKey].ID,
						text: accountCustomFields[cfKey].NAME,
						class_name: field_class[accountCustomFields[cfKey].TYPE_ID]
					});
				}
			}
			// сохраним список полей для новых условий
			_fields = anumFields;
			$.ajax({
				url: urlAPI + '/process',
				method: 'POST',
				dataType: 'json',
				data: {
					process_id: process_id !== '' ? process_id : 0
				},
				success: function(res) {
					if(!res.error) {					
						self.getTemplate('anum_settings', function(data) {
							let params = {
								path: self.base_path,
								conditions: res.data.process ? $.parseJSON(res.data.process.conditions) : res.data.process,
								self: self,
								fields: anumFields,
								masks: maskTemplates
							};

							$(self.id()).find('.settings-loader-wrapper').replaceWith(data.render(params));
							
							// подключим select2 к списку полей
							$('.js-anum_field_select').select2({
								data:_fields,
								placeholder: 'Select an option',
								width:'100%',
								dropdownParent:$(self.id()),
								templateResult: (state)=> {
									if (!state.id) return state.text;
									return $('<span class="'+state.class_name+'">'+ state.text + '</span>');
								},
							})

							// выберем нужный пункт
							$('.js-anum_field_select').each(function(indx){
								let id = $(this).attr('data-selected-id')
								$(this).val(id);
								$(this).trigger('change');
							})
								
						});
					} else {
						self.openAlert(res.data.msg, 'error');
						self.dp_loader(false);
					}
				},
				error: function(jqxhr, status, errorMsg) {
					self.openAlert(error_msg, 'error');
					self.dp_loader(false);
				}
			});
		};

		// Сохранение процесса
		this.saveDpProcess = function() {
			// получим значения настроек
			let conditions = [], next = true;
			$(self.id()).find('.amoai-autonumber_lead-dp_setting .amoai-autonumber_lead-field_condition').each(function(indx){
				let select = $(this).find('.js-anum_field_select').select2('data');
				// id поля 
                let anum_field = +select[0].id
				// маска автонумерации
				let anum_tpl = $(this).find('.input_anum_tpl').val();
				// тип поля число/текст
				let anum_field_type;
				if(select[0].class_name === 'amoai-numeric_field'){
					anum_field_type = 'numeric';
				} else {
					anum_field_type = 'text';
				}

				if(anum_tpl === ''){
					$(self.id()).find('.digital-pipeline__warning-container').text('Необходимо указать маску автонумерации!');
					next = false;
					return false;
				}	

				conditions.push({
					anum_tpl: anum_tpl,
					anum_field: anum_field,
					anum_field_type: anum_field_type,
				})
			})
			if(!next){
				self.dp_loader(false);
				return false;
			}
			return new Promise(function(succeed, fail) {
				$.ajax({
					url: urlAPI + '/saveProcess',
					method: 'POST',
					data: {
						account_id:   AMOCRM.constant('account').id,
						process_id:   $(self.id()).find('input[name="params"]').val(),
						conditions:   conditions,
					},
					dataType: 'json',
					success: function(res) {
						succeed(res);
					},
					error: function(jqxhr, status, errorMsg) {
						fail(new Error("Request failed: " + errorMsg));
					}
				});
			});
		};

		// Сохранение настроек процесса
		this.saveDpSettings = function() {
			self.dp_loader(true);
			return self.getWidgetStatus().then(function(res) {
				if(typeof res.data.active !== 'undefined' && res.data.active) {
					return self.saveDpProcess().then(function(res) {
						self.dp_loader(false);
						let result = res.error ? false : true;
						if(result) {
							$(self.id()).find('input[name="params"]').val(res.data.process_id);
						}
						let type = res.error ? 'error' : 'success';
						self.openAlert(res.data.msg, type);
						return result;
					}, function(error) {
						self.dp_loader(false);
						self.openAlert(error_msg, 'error');
						return false;
					});
				} else {
					self.openAlert(res.data.msg, 'error');
					self.dp_loader(false);
					return false;
				}
			}, function(error) {
				self.dp_loader(false);
				self.openAlert(error_msg, 'error');
				return false;
			});
		};

		// Удаление процесса
		this.deleteDpProcess = function(process_id) {
			return new Promise(function(succeed, fail) {
				$.ajax({
					url: urlAPI +  '/deleteProcess',
					method: 'POST',
					dataType: 'json',
					data: {
						account_id: AMOCRM.constant('account').id,
						process_id: process_id
					},
					success: function(res) {
						succeed(res);
					},
					error: function(jqxhr, status, errorMsg) {
						fail(new Error("Request failed: " + errorMsg));
					}
				});
			});
		};
		
        this.callbacks = {
            render: function() {
				return true;
            },
            init: function(){
				self.base_path = urlAPI + '/widget/installer';

                return true;
            },
            bind_actions: function() {
				let $body = $('body');
            	// Выбор маски
				$body.off('click', '.amoai-autonumber_lead-dp_settings .tpl_item a');
				$body.on('click', '.amoai-autonumber_lead-dp_settings .tpl_item a', function(e) {
					e.preventDefault();
					let anumTpl = $(this).closest('.amoai-autonumber_lead-field_condition').find('input[name=anum_tpl]');

					anumTpl.val(anumTpl.val() + $(this).text()).trigger('change');					
				});

				// Добавить условие
				$body.off('click', '.amoai-autonumber_lead-insert_conditions');
				$body.on('click', '.amoai-autonumber_lead-insert_conditions', function(e){
					e.stopPropagation();
					let $container = $(this).parents('.amoai-autonumber_lead-dp_settings_container').find('.amoai-autonumber_lead-dp_setting');
					let params = {
						self: self,
						fields: _fields,
						masks: maskTemplates,
						deleteable:true
					}
					self.getTemplate('field_group', function(data){
						$container.append(data.render(params))
						$container.find('.amoai-autonumber_lead-field_condition:last-child')
						    .find('.js-anum_field_select').select2({
									data:_fields,
									placeholder: 'Select an option',
									width:'100%',
									dropdownParent:$(self.id()),
									templateResult: (state)=> {
										if (!state.id) return state.text;
										return $('<span class="'+state.class_name+'">'+ state.text + '</span>');
									},
								})
					})
				})

				// удалить условие
				$body.off('click', '.amoai-autonumber_lead-field_condition .condition_delete');
				$body.on('click', '.amoai-autonumber_lead-field_condition .condition_delete', function(e){
					e.stopPropagation();
					$(this).parents('.amoai-autonumber_lead-field_condition').remove();
				})

           		// Open form "Buy"
    			$body.off('click', `#${ai_widget_code}_buy_widget_btn`);
    			$body.on('click', `#${ai_widget_code}_buy_widget_btn`, function() {
    				self.openOrderForm();
    			});

           		// Send form "Buy"
    			$body.off('click', self.id() + '_buy_widget_send');
    			$body.on('click', self.id() + '_buy_widget_send', function() {
    				self.sendOrderForm($(this));
    			});

            	return true;
            },
            settings: function () {
				const $modal = $('.widget-settings__modal.' + self.params.widget_code);
				const $save = $modal.find('button.js-widget-save');

				$modal.attr('id', self.id('', false)).addClass('amoai-settings');

				self.getWidgetSettings().then(function (data) {

					// Add style
					self.appendCss('/css/style.css');

					// Add description
					$modal.find('.widget_settings_block__descr').html(data.data.description);

					// Add footer
					$modal.find('.widget-settings__wrap-desc-space').append(data.data.footer);

					// Add confirm
					$modal.find('.widget_settings_block__fields').append(data.data.confirm);

					// Статус виджета
					self.getWidgetStatus().then(function (res) {

						// Add status text
						$modal.find('.amoai_settings_payment_info_text').text(res.data.msg).css("color", res.data.active ? "#749e42" : "#ff7779");

						if (self.get_install_status() != 'install') {
							if (res.error) {

								// Add warning
								$modal.find('.widget_settings_block__descr').prepend(data.data.warning);

								// Activate btn
								$save.find('.button-input-inner__text').text('Активировать виджет');
								$save.trigger('button:enable').addClass('amoai-settings-activate_btn');

								// Автозаполнение телефона
								let is_admin = AMOCRM.constant('managers')[AMOCRM.constant('user').id].is_admin;

								if (is_admin == 'Y') {
									if ($modal.find('input[name="phone"]').val() == '' && AMOCRM.constant('user').personal_mobile != '') {
										$modal.find('input[name="phone"]').val(AMOCRM.constant('user').personal_mobile);
									}
								}

								// Add mask
								$modal.find('input[name="phone"]').intlTelInput({
									initialCountry: 'ru',
									onlyCountries: ['ru', 'by', 'kz', 'ua']
								});

								// Активировать виджет
								let save_flag = false;
								$save.off('click').on('click', function () {

									if (save_flag) {
										save_flag = false;

										return true;
									}

									// Если не заполнено поле "Телефон"
									if ($modal.find('input[name="phone"]').val() == '') {
										self.openAlert('Заполните номер телефона!', 'error');

										return false;
									}

									// Если не дали согласие на передачу данных
									if ($modal.find('#amoai_settings_confirm').prop('checked') === false) {
										self.openAlert('Вам необходимо дать согласие на передачу данных из amoCRM.', 'error');

										return false;
									}

									// Активация виджета
									self.installWidget($save).then(function (res) {
										let type = res.error ? 'error' : 'success';
										self.openAlert(res.msg, type);

										if (type == 'success') {
											save_flag = true;
											$save.trigger('click');
										}
									}, function (error) {
										self.openAlert(error_msg, 'error');
									});

									return false;
								});
							} else {
								// Deactive btn & hide warning
								$modal.find('.widget_settings_block__fields').hide();

								// Activate widget in amo
								if (self.get_install_status() == 'not_configured') {
									$save.trigger('click');
								}
							}
						} else {
							// Deactive btn & hide warning
							$modal.find('.widget_settings_block__fields').hide();
						}
					}, function (error) {
						self.openAlert(error_msg, 'error');
					});
				}, function (error) {
					self.openAlert(error_msg, 'error');
				});

                return true;
            },
			dpSettings: function () {
				self.getWidgetStatus().then(function (res) {
					if (res.data.active) {
						self.appendCss('/css/style.css');
						self.loadStyle('https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
		
						const $save   = $('button.js-trigger-save');
						const $cancel = $('button.js-trigger-cancel');
						const $delete = $('svg.digital-pipeline__edit-delete');
						const $modal  = $save.closest('[data-action=send_widget_hook]');
		
						$modal.attr('id', self.id('', false)).addClass('amoai-autonumber_lead-dp_settings custom-scroll');
		
						$modal.find('.digital-pipeline__edit-forms .task-edit__body__form').hide();
						 $modal.find('.task-edit__body__form').after('<div class="amoai-autonumber_lead-dp_settings_container"></div>');
		
						 // Получить настройки
						self.getDpSettings();
		
						 // Сохранить настройки процесса
						var save_flag = false;
						$save.off(AMOCRM.click_event).on(AMOCRM.click_event, function() {
							if(save_flag) {
								save_flag = false;		
								return true;
							}		
							self.saveDpSettings().then(function(result) {
								if(result) {
									save_flag = true;
									$save.trigger('click');		
									$('.digital-pipeline__save-button').trigger('click');
								}
							});		
							return false;
						});
		
						// Удалить процесс
						$delete.off(AMOCRM.click_event).on(AMOCRM.click_event, function () {
							self.deleteDpProcess($(self.id()).find('input[name="params"]').val()).then(function (res) {
								self.openAlert(res.data.msg, res.error ? 'error' : 'success');		
								if(!res.error) {
									$('.digital-pipeline__save-button').trigger('click');
								}
							 });
						});
					}	else {
							self.openNotifications(res.data.msg, 'error');
						}
				}, function (error) {
					self.openNotifications(error_msg, 'error');
				});				

			   	return true;
			},
            onSave: function(){
                return true;
            },
            destroy: function(){}
        };

        return this;

    };

    return CustomWidget;
});