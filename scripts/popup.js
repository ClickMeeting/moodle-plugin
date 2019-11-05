/*global window, screen, opener, $, jQuery */
if (!Object.create) {
    Object.create = function (o) {
        function F() {}
        F.prototype = o;
        return new F();
    };
}

(function (proto) {
    if (!Array.isArray) {
        Array.isArray = function (o) {
            return Object.prototype.toString.call(o) === '[object Array]';
        };
    }

    if (!proto.indexOf) {
        proto.indexOf = function (item, start) {
            var i = 0,
                ln = this.length;
            start = start || 0;
            for (i = start; i < ln; i += 1) {
                if (this[i] === item) {
                    return i;
                }
            }
            return -1;
        };
    }
    proto.contains = function (item) {
        return this.indexOf(item) > -1;
    };
})(Array.prototype);

(function(){
    // remove layerX and layerY
    var all = $.event.props,
        ln = all.length,
        res = [],
        el;
    while (ln--) {
      el = all[ln];
      if (el !== 'layerX' && el !== 'layerY') {
          res.push(el);
      }
    }
    $.event.props = res;
})();

(function (app) {
    var $ = /*app.DOM || */app || function () {},
        hasOwn = Object.prototype.hasOwnProperty,
        win = window,
        doc = win.document,
        timer,
        cycle = 0,
        opened = {},
        def = {
            scrollbars: 'yes',
            channelmode: 'no',
            directories: 'no',
            fullscreen: 'no',
            resizable: 'no',
            toolbar: 'no',
            location: 'no',
            menubar: 'no',
            status: 'no',
            titlebar: 'no',
            width: 1,
            height: 1,
            left: 0,
            top: 0
        },
        minWidth = 800,
        minHeight = 600,
        popup;

    if (!win.POPUP) {
        win.POPUP = {};
    }

    $.getById = function (id) {
        return doc.getElementById(id);
    };

    observer = {
        listeners: {},
        addListener: function (name, fn, overwrite) {
            var ix;

            if (!this.listeners[name]) {
                this.listeners[name] = [];
            }

            ix = this.listeners[name].indexOf(fn);

            if ('function' === typeof fn && (ix === -1 || overwrite)) {
                ix = ix > -1 ? ix : this.listeners[name].length;
                this.listeners[name].splice(ix, 1, fn);
            }

            return this;
        },
        addListeners: function (ob) {
            var k;

            if ('object' === typeof ob) {
                for (k in ob) {
                    if ('object' === typeof ob[k]) {
                        this.addListeners(ob[k]);
                        continue;
                    }
                    this.addListener(k, ob[k]);
                }
            }

            return this;
        },
        removeListener: function (name, fn) {
            var ix;

            if (this.listeners[name]) {
                ix = this.listeners[name].indexOf(fn);

                if (ix > -1) {
                    this.listeners[name].splice(ix, 1);
                }
            }

            return this;
        },
        notify: function (name) {
            var i,
                ln,
                list = [],
                ret;
            if (!this.listeners[name]) {
                return;
            }

            for (i = 0, ln = this.listeners[name].length; i < ln; i += 1) {
                ret = this.listeners[name][i].apply(this, Array.prototype.slice.call(arguments, 1));
                ret && list.push(ret);
            }
            return list;
        }
    };
    popup = Object.create(observer);
    popup.setParams = function (params) {
        var k, ret = [];

        params = params || {};

        this.params.width = params.width || 1;
        this.params.height = params.height || 1;
        this.params.screenX = this.params.left;
        this.params.screenY = this.params.top;
		this.params.scrollbars = 1;

        for (k in this.params) {
            if (hasOwn.call(this.params, k)) {
                ret.push(k + '=' + this.params[k]);
            }
        }

        return ret.join(',');
    };
    popup.init = function (data) {
        this.url = data.url;
        this.name = data.name || 'ROOM';
        this.params = Object.create(def);

        this.paramStr = this.setParams(data.params);

        if (data.listeners) {
            this.addListeners(data.listeners);
        }

        return this;
    };

    function resizePopup(popup, w, h) {
        w = Math.max((w || screen.width), minWidth);
        h = Math.max((h || screen.height), minHeight);

        popup && popup.moveTo(0, 0);

        clearTimeout(timer);
        timer = setTimeout(function () {
            popup.resizeTo(w, h);

            if (cycle > 10) {
                clearTimeout(timer);
                cycle = 0;
                return;
            }
            cycle += 1;
        }, 50);

        popup.focus();
    }

    function onSubmit(elem, data) {
        var p, w;

        p = Object.create(popup).init(data);

        return function (e) {
            var opened = win.POPUP && win.POPUP[p.name],
                progressUrl = data.progressUrl || this.getAttribute('data-progress');

            if ('function' === typeof data.onBeforeSubmit) {
                if (!data.onBeforeSubmit()) {
                    e.preventDefault();
                    return;
                }
            }

            if (opened) {
                if (!opened.closed) {
                    return;
                }
            }

            w = win.open('about:blank', p.name, p.paramStr);

            if (!w || w.closed) {
                win.location.href = data && data.url;
                return;
            }

            e.preventDefault();

            elem.target = p.name;

            win.POPUP[p.name] = w;

            if (data.url) {
                w.location.href = data.url;
            }
            else {
                if ('FORM' === elem.nodeName.toUpperCase()) {
                    elem.submit();
                }
                else {
                    w.location.href = elem.href;
                }
            }

            if ('function' === typeof data.onAfterSubmit) {
                if (!opener) {
                    data.onAfterSubmit();
                }
            }

            if (progressUrl) {
                win.location.href = progressUrl;
            }

            resizePopup(w);

            w.focus();

            w.onunload = function () {
                p.notify('onUnload', p, w.opener);
            };
        };
    }

    if (app.fn) {
        app.fn.submitToPopup = function (form, data) {
            var i = 0,
                ln = this.length;

            for (; i < ln; i += 1) {
                $(this[i]).bind('click', onSubmit(form || this[i], data || {}));
            }
        };
    }
    app.ajaxToPopup = function (data) {
        var p, w,
            opened;

        p = Object.create(popup).init(data);
        
        opened = win.POPUP && win.POPUP[p.name];

        if (win.name === p.name) {
            return;
        }

        if ('function' === typeof data.onBeforeSubmit) {
            if (!data.onBeforeSubmit()) {
                return;
            }
        }

        if (opened) {
            if (!opened.closed) {
                opened.close();
                //return;
            }
        }

        w = win.open('about:blank', p.name, p.paramStr);

        win.POPUP[p.name] = w;

        w.onunload = function () {
            p.notify('onUnload', p, w.opener);
        };

        return {
            popup: w,
            doRedirect: function (o) {
                var url = data.url || o.url,
                    progressUrl = data.progress_url || o.progress_url;

                if (w) {
                    if (url) {
                        w.location.href = url;
                    }

                    if (progressUrl) {
                        win.location.href = progressUrl;
                    }
                }
                else {
                    win.location.href = url;
                }

                if ('function' === typeof data.onAfterSubmit) {
                    if (!opener) {
                        data.onAfterSubmit();
                    }
                }
                
                try {
                    w.onload = function () {
                        this.focus();
                    };

                    resizePopup(w);
                }
                catch (ex) {
                    win.location.href = url;
                    win.focus();
                }
            },
            close: function () {
                w.close();
            }
        };
    };
})(window.cQuery || this);
