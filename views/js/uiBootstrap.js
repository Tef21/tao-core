/**
 * UiBootstrap class enable you to run the naviguation mode,
 * bind the events on the main components and initialize handlers
 *
 *
 * @require jquery >= 1.3.2 [http://jquery.com/]
 * @require [helpers.js]
 *
 * @author Bertrand Chevrier, <bertrand.chevrier@tudor.lu>
 * @author Jehan Bihin (using class.js)
 */

define(['require', 'jquery', 'tao.tabs', root_url + '/filemanager/views/js/jquery.fmRunner.js', 'class'], function(req, $) {
	var UiBootstrap = Class.extend({
		init: function(options) {
			this.initAjax();
			this.initNav();
			this.initMenuBar();

			//create tabs
			this.tabs = $('#tabs').tabs({
				load: function(){
					$("#section-trees").empty().css({display: 'none'});
					$("#section-actions").empty().css({display: 'none'});
					uiBootstrap.initTrees();
				},
				select: function(event, ui) {
					$("#section-trees").empty().css({display: 'none'});
					$("#section-actions").empty().css({display: 'none'});
					$("#" + uiBootstrap.tabs.attr('id') + " > .ui-tabs-panel").each(function(){
						if($(this).attr('id') != ui.panel.id){
							$(this).empty();
						}
					});
				}
			});

			//Enable the closing tab if added after the init
			this.tabs.tabs("option", "tabTemplate", '<li class="closable"><a href="#{href}"><span>#{label}</span></a><span class="tab-closer" title="'+__('Close tab')+'">X</span></li>');
			this.tabs.on("tabsadd", function(event, ui) {
				//Close the new content div
				$(ui.panel).addClass('ui-tabs-hide');
			});
			//Closer tab icon
			$(document).on('click', '.tab-closer', function(e) {
				e.preventDefault();
				uiBootstrap.tabs.tabs('remove', $(this).parent().index());
				//Select another by default ?
				uiBootstrap.tabs.tabs('select', 0);
			});
		},

		/**
		 * initialize common ajavx behavior
		 */
		initAjax: function(){
			//just before an ajax request
			$("body").ajaxSend(function(event,request, settings){
				helpers.loading();
			});

			//when an ajax request complete
			$("body").ajaxComplete(function(event, request, settings){
				helpers.loaded();

				if (settings.dataType == 'html') {
					helpers._autoFx();
					if(/add|edit|Instance|Class|getSectionTrees/.test(settings.url) && !/authoring/i.test(settings.url)){
						//Removed: search|
						uiBootstrap.initActions();
					}
					if(!/getMetaData/.test(settings.url)){
						$("#section-meta").empty();
					}
					uiBootstrap.initSize();
				}
			});

			//intercept errors
			$("body").ajaxError(function(event, request, settings){
				if(request.status == 404 || request.status == 500){
					helpers.createErrorMessage(request.responseText);
				}
				if(request.status == 403){
					window.location = root_url + '/tao/Main/logout';
				}
			});
		},

		/**
		 * initialize common naviguation
		 */
		initNav: function(){
			//load the links target into the main container instead of loading a new page
			$('a.nav').live('click', function() {
				try{
					helpers._load(helpers.getMainContainerSelector(helpers.tabs), this.href);
				}
				catch(exp){return false;}
				return false;
			});
		},

		/**
		 * initialize the tree component
		 */
		initTrees: function(){
			//left menu trees init by loading the tab content
			if(this.tabs.length > 0){
				section = $("li a[href=#" + $('.ui-tabs-panel')[this.tabs.tabs('option', 'selected')].id + "]:first").attr('title');
				if (section != undefined) {
					$.ajax({
						url: root_url + '/' + currentExtension + '/Main/getSectionTrees',
						type: "GET",
						data: {
							section: section,		//get the link text of the selected tab
							structure: currentStructure
						},
						dataType: 'html',
						success: function(response){
							if(response == ''){
								$('#section-trees').css({display: 'none'});
							}
							else if($('#section-trees').css('display') == 'none'){
								$('#section-trees').css({display: 'block'});
							}
							$('#section-trees').html(response);
						}
					});
				}
			}
		},

		/**
		 * initialize the actions component
		 */
		initActions: function(){
			//left menu actions init by loading the tab content
			if(this.tabs.length > 0){
				$.ajax({
					url: root_url + '/' + currentExtension + '/Main/getSectionActions',
					type: "GET",
					data: {
						section: $("li a[href=#" + $('.ui-tabs-panel')[this.tabs.tabs('option', 'selected')].id + "]:first").attr('title'),		//get the link text of the selected tab
						structure: currentStructure
					},
					dataType: 'html',
					success: function(response){
						if(response == '') {
							$('#section-actions').css({display: 'none'});
						}
						else if($('#section-actions').css('display') == 'none') {
							$('#section-actions').css({display: 'block'});
						}
						$('#section-actions').html(response);
						eventMgr.trigger('actionInitiated', [response]);
					}
				});
			}
		},

		/**
		 * re-calculate the container size regarding the components content
		 */
		initSize: function(){
			//set up the container size
			myPanel = $('.ui-tabs-panel')[this.tabs.tabs('option', 'selected')];
			if(myPanel){
				uiTab = myPanel.id;
				if($('#section-actions').html() == '' && $('#section-trees').html()  == '' && $("div#"+uiTab).css('width') == '79.5%' ){
					$("div#"+uiTab).css({'width': '100%', 'left': 0});
				}
				if( $('#section-actions').html() != '' || $('#section-trees').html()  != '' ){
					$("div#"+uiTab).css({'width': '79.5%', 'float': 'right'});
				}
			}
		},

		initMenuBar: function(){
			//add a focus selector
			var lastFocussed = null;
			$(':text').live('focus',function(){
				lastFocussed = this;
			});

			//initialize the media manager menu
			$("#main-menu .file-manager").fmload({type: 'file'}, lastFocussed, function(element, url){
				if(lastFocussed != null){
					$(lastFocussed).val($(lastFocussed).val() + url);
				}
			});

			//initialize the settings menu
			$("#main-menu .settings-loader").click(function(){
				this._load(helpers.getMainContainerSelector(uiBootstrap.tabs), this.href);
				return false;
			});
		}

	});

	return UiBootstrap;
});