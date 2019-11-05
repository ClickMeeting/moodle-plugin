function goToUrl(thisObj, initialize, callback){
    if ($.browser.msie && parseInt($.browser.version) < 7)
        return true;
  var locationHref = top.location.href.toLowerCase(),
    backUrl = '/conferences.html';
  if (locationHref.indexOf('backurl=') != -1){var tmpLocationUrl = top.location.href.substr(locationHref.indexOf('backurl=') + 8).split('&')[0];if (tmpLocationUrl) backUrl = tmpLocationUrl}
  if (!initialize) initialize = 'fnInit'; else if (initialize == 'fnDestroy') if (locationHref.indexOf('loadpopupbody=true') != -1 && backUrl != null) location.href = backUrl;
  return $(thisObj).popupLight({initialize:initialize,backUrl:backUrl, callback: callback || {}});
}

(function($){
  $.fn.popupLight=function(options){
    options=$.extend({initialize:'fnInit',backUrl:null,redirect:false,newwindow:false,iframe:false,exitFunction:null,hideElems:['embed', 'object'],selectors:{overlay:'#overlay',content:'#content',tmpDiv:'#tmpPopupLight',windowDiv:'#window',loader:'.preloader',inner:'#inner',close:'#closeLabel',showHide:'.showHideExtraOptions',oneColView:'oneColView',secondCol:'ul.poUpsCols li.pUCols-Sec',firstCol:'ul.poUpsCols li.pUCols-First',sidebar:'#popupSidebar',dspNone:'dspNone',bulletLeft:'arrowBulletTurnLeftR',bulletRight:'arrowBulletR'},width:0,height:0,dimmisionsFrom:null,ajax:{url:'', data:{xhr : true}},tagData:{tag:'a',type:'rel',dataType:'popupLight'},translate:{showOptions:'Show options',hideOptions:'Hide options'}},options||{});
    var isRequest=false,defaultHeight=0,sUrl=options.ajax.url!=''?options.ajax.url:($(this).attr('tagName')=='A'?$(this).attr('href'):$(this).attr('tagName')=='form'?$(this).attr('action'):''),openedBar=null,rtime=new Date(1, 1, 2000, 12,00,00),timeout=false,delta=200;
    $(document).keydown(function(e){if (e.which==27&&$(options.selectors.overlay).length)fnDestroy()});

    var fnInit = function(){
      if ($(options.selectors.overlay).length)
        fnReRender();
      else{
        fnDestroy(false);
        fnRender();
      }
      fnLoadData();
    };

    var fnLoadData = function(){
      var timer = setInterval(function(){
        if (options.redirect){
          location.href = options.ajax.url;
          options.redirect = false;
        }
        else if (options.newwindow){
          window.open(options.ajax.url);
          options.newwindow = false;
          $(options.selectors.loader).hide();
        }
        else{
          if($(options.selectors.overlay).length){
            if (options.iframe){
              $(options.selectors.loader).hide();
              $(options.selectors.close).show();
              if (options.dimmisionsFrom != null) $.post(sUrl, options.ajax.data, function(html){$(options.selectors.inner+'_wrapper').prepend($('<iframe src ="'+sUrl+'" width="'+parseInt($(html).find(options.dimmisionsFrom).css('width'))+'" height="'+parseInt($(html).find(options.dimmisionsFrom).css('height'))+'" />'))});
              else $(options.selectors.inner+'_wrapper').prepend($('<iframe src ="'+sUrl+'" width="'+options.width+'" height="'+options.height+'" />'));
            }
            else{
              $(options.selectors.inner+'_wrapper').load(sUrl, options.ajax.data, function(html){
                if ($('.innerWrapper', html).length < 3){
					if (html == '') html = '<div style="width:380px; text-align:center" class="innerWrapper"><h2 class="cufFont">The requested URL does not exist!</h2></div>';
					$(options.selectors.loader).hide();
					$(options.selectors.close).show();

					var width = parseInt($(options.selectors.tmpDiv).find(':first').innerWidth()),
						height = parseInt($(options.selectors.tmpDiv).find(':first').innerHeight()) + 22;
					
					defaultHeight = height;

                                  $(options.selectors.tmpDiv).animate({width:width, height:height},150,function(){
                                      if(parseFloat(navigator.appVersion.split('MSIE')[1]) < 8)
                                          $(options.selectors.content).width(width);
                                      $(this).css({'height':'auto', 'visibility':'visible'});
                                      $(this).find(':first').css({'height':'auto','width':'auto'});
                                      $(options.selectors.close).show();
                                      $(options.selectors.tmpDiv).find('input[type=text]:first').focus();

                                      fnSetFlexible(height, false);
                                      $('input#conference_phone_access').bind('click', function(){fnSetFlexible(parseInt($(options.selectors.tmpDiv).find(':first').innerHeight()), true)});

                                      $(options.tagData.tag +'['+ options.tagData.type +'='+ options.tagData.dataType +']', this).bind('click.popup', function(){sUrl = $(this).attr('href');fnInit()})
                                  });
                              }

					if (options.callback && $.isFunction(options.callback.load)) {
                  options.callback.load();
                }
              }, 'html')
            }
            clearInterval(timer);
          }
        }
      },500);
    };
        var fnResizeEnd = function(params){
          if(new Date()-rtime<delta)
        setTimeout(function(){
          fnResizeEnd(params);
        }, delta);
      else{
        timeout=false;
        fnSetFlexible(params.height, params.flex);
      }
        };
    var fnSetFlexible = function(height, flex){
      $(window).resize(function(){
        rtime = new Date();
        if (timeout===false){
          timeout=true;
          setTimeout(function(){
            fnResizeEnd({height:height, flex:true});
          }, delta);
        }
      });

      height += 40;
      if (height >= $(window).height()){
        $(options.selectors.overlay).css({'position':'absolute','height':$(document).height()});
        $(options.selectors.windowDiv).css({'vertical-align':'top'});
        $(options.selectors.content).css({'margin-top':(parseInt($(window).scrollTop())+20)+'px'});
      }
      else{
        if (flex){
          $(options.selectors.overlay).css({'position':'fixed','height':$(window).height()});
          $(options.selectors.windowDiv).css({'vertical-align':'middle'});
          $(options.selectors.content).css({'margin-top':'10px'});
        }
      }
    };
    var fnResize = function(width){
      width += (1 + parseInt($(options.selectors.secondCol).innerWidth()));
      if (parseInt($(options.selectors.firstCol).innerHeight()) < parseInt($(options.selectors.secondCol).innerHeight())){
        $(options.selectors.tmpDiv).animate({height:parseInt($(options.selectors.secondCol).innerHeight()), width:width});
        $(options.selectors.firstCol).find(':first').animate({height:parseInt($(options.selectors.secondCol).innerHeight())})
      }
      else{
        $(options.selectors.tmpDiv).animate({width:width});
        $(options.selectors.secondCol).animate({height:defaultHeight})
        $(options.selectors.firstCol).find(':first').animate({height:defaultHeight})
      }
      $(options.selectors.inner+'_wrapper').css('width', '100%');
    };
    var fnHTMLStruct = function(){
      if ($(options.selectors.loader).length == 0)
      return $('<div '+fnSetSelector(options.selectors.windowDiv)+'="'+options.selectors.windowDiv.substr(1)+'"/>').prepend(
        $('<div '+fnSetSelector(options.selectors.content)+'="'+options.selectors.content.substr(1)+'"/>').prepend(
          $('<div '+fnSetSelector(options.selectors.inner)+'="'+options.selectors.inner.substr(1)+'"/>').prepend(
            $('<div id="'+options.selectors.inner.substr(1)+'_wrapper"/>').prepend(
                          $('<div '+fnSetSelector(options.selectors.loader)+'="'+options.selectors.loader.substr(1)+'"/>')
            ),
            $('<div '+fnSetSelector(options.selectors.close)+'="'+options.selectors.close.substr(1)+'"/>').hide().click(fnDestroy)
          )
        )
      ).bind('click', function(event){if (event.target.id == options.selectors.windowDiv.substr(1)) fnDestroy()});
    };
    var fnRender = function(){
      $('body').prepend($('<div '+fnSetSelector(options.selectors.overlay)+'="'+options.selectors.overlay.substr(1)+'"/>').fadeIn('fast', function(){
        $(this).prepend($('<div id="windowBlend" />').css({'opacity':'0.4','height':$(document).height()}));
        $(this).append(fnHTMLStruct());
      }));
    };
    var fnReRender = function(){
      $(options.selectors.windowDiv).remove();
      $(options.selectors.overlay).append(fnHTMLStruct());
      $(options.selectors.overlay).css({'position':'fixed','height':$(window).height()});
      $(options.selectors.windowDiv).css({'vertical-align':'middle'});
      $(options.selectors.content).css({'margin-top':'10px'});
    };
    var fnDestroy = function(redirect){
      if(redirect !== false && typeof redirect != 'undefined')if (top.location.href.toLowerCase().indexOf('loadpopupbody=true') != -1 && options.backUrl != null) location.href = options.backUrl;
      $(options.selectors.loader, options.selectors.close).remove();
      $(options.selectors.content).fadeOut('fast', function(){
            $(this).remove();
            $(options.selectors.overlay).fadeOut('fast', function(){
              $(this).remove();
              for (var i=0, c=options.hideElems.length; i<c; i++)
                $(options.hideElems[i]).css('visibility','visible')
            })
          })
          if (options.exitFunction != null) options.exitFunction();
      $(this).unbind('click.popup');
      if (options.callback && $.isFunction(options.callback.unload)) {
        options.callback.unload();
      }
    };
    var fnSetSelector = function(selector){
          return (/^#.*$/.test(selector) ? 'id' : 'class')
        };
    eval(options.initialize + '()');
    return false
  }
})(window.cQuery);