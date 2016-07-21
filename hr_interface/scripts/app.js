(function ($) {
	'use strict';

	  window.app = {
      name: 'Flatkit',
      version: '1.1.0',
      // for chart colors
      color: {
        'primary':      '#0cc2aa',
        'accent':       '#a88add',
        'warn':         '#fcc100',
        'info':         '#6887ff',
        'success':      '#6cc788',
        'warning':      '#f77a99',
        'danger':       '#f44455',
        'white':        '#ffffff',
        'light':        '#f1f2f3',
        'dark':         '#2e3e4e',
        'black':        '#2a2b3c'
      },
      setting: {
        theme: {
    			primary: 'primary',
    			accent: 'accent',
    			warn: 'warn'
        },
        color: {
	        primary:      '#0cc2aa',
	        accent:       '#a88add',
	        warn:         '#fcc100'
    	  },
        folded: false,
        boxed: false,
        container: false,
        themeID: 1,
        bg: ''
      }
    };

    var setting = 'jqStorage-'+app.name+'-Setting',
        storage = $.localStorage;
    
    if( storage.isEmpty(setting) ){
        storage.set(setting, app.setting);
    }else{
        app.setting = storage.get(setting);
    }

    if(getParams('bg')){
      app.setting.bg = getParams('bg');
      storage.set(setting, app.setting);
    }

    // init
    $('body').addClass(app.setting.bg);
    app.setting.boxed && $('body').addClass('container');
    app.setting.folded && $('#aside').addClass('folded');
    setTimeout(function() {
        $('[ng-model="app.setting.folded"]').prop('checked', app.setting.folded);
        $('[ng-model="app.setting.boxed"]').prop('checked', app.setting.boxed);
        $('#settingColor input[value='+app.setting.themeID+']').prop('checked', 'checked');
    }, 1000);
    
    // folded, boxed, container
    $(document).on('change', '#settingLayout input', function(e){
         eval( $(this).attr('ng-model') +"="+ $(this).prop('checked') );
         storage.set(setting, app.setting);
         location.reload();
    });
    // color and theme
    $(document).on('click', '[ng-click]', function (e) {
        eval($(this).attr('ng-click'));
        if($(this).find('input')){
          app.setting.themeID = $(this).find('input').val();
        }
        storage.set(setting, app.setting);
        location.reload();
    });

    init();

    function setTheme(theme){
      app.setting.theme = theme.theme;
      setColor();
      if(theme.url){
        setTimeout(function() {
          var layout = theme.url.split('=');
          window.location.href = 'dashboard.'+( layout[1] ? layout[1]+'.' : '')+'html';
        }, 1);
      }
    };

    function setColor(){
      app.setting.color = {
        primary: getColor( app.setting.theme.primary ),
        accent: getColor( app.setting.theme.accent ),
        warn: getColor( app.setting.theme.warn )
      };
    };

    function getColor(name){
      return app.color[ name ] ? app.color[ name ] : palette.find(name);
    };

    function getParams(name) {
      name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
      var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
          results = regex.exec(location.search);
      return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
    }

    function init(){
      $('[ui-jp]').uiJp();
      $('body').uiInclude();
    }

})(jQuery);
