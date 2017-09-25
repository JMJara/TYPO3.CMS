'use strict';(function(d){"object"==typeof exports&&"object"==typeof module?d(require("../../lib/codemirror")):"function"==typeof define&&define.amd?define(["../../lib/codemirror"],d):d(CodeMirror)})(function(d){function m(a,b){for(var k=0,e=a.length;k<e;++k)b(a[k])}function p(a,b,k,e){var f,g=a.getCursor(),c=k(a,g);if(!/\b(?:string|comment)\b/.test(c.type)){c.state=d.innerMode(a.getMode(),c.state).state;/^[\w$_]*$/.test(c.string)?c.end>g.ch&&(c.end=g.ch,c.string=c.string.slice(0,g.ch-c.start)):c=
{start:g.ch,end:g.ch,string:"",state:c.state,type:"."==c.string?"property":null};for(var l=c;"property"==l.type;){l=k(a,n(g.line,l.start));if("."!=l.string)return;l=k(a,n(g.line,l.start));f||(f=[]);f.push(l)}return{list:q(c,f,b,e),from:n(g.line,c.start),to:n(g.line,c.end)}}}function r(a,b){a=a.getTokenAt(b);b.ch==a.start+1&&"."==a.string.charAt(0)?(a.end=a.start,a.string=".",a.type="property"):/^\.[\w$_]*$/.test(a.string)&&(a.type="property",a.start++,a.string=a.string.replace(/\./,""));return a}
function q(a,b,k,e){function f(a){var b;if(b=0==a.lastIndexOf(l,0)){a:if(Array.prototype.indexOf)b=-1!=c.indexOf(a);else{for(b=c.length;b--;)if(c[b]===a){b=!0;break a}b=!1}b=!b}b&&c.push(a)}function g(a){"string"==typeof a?m(t,f):a instanceof Array?m(u,f):a instanceof Function&&m(v,f);if(Object.getOwnPropertyNames&&Object.getPrototypeOf)for(;a;a=Object.getPrototypeOf(a))Object.getOwnPropertyNames(a).forEach(f);else for(var b in a)f(b)}var c=[],l=a.string,d=e&&e.globalScope||window;if(b&&b.length){a=
b.pop();var h;a.type&&0===a.type.indexOf("variable")?(e&&e.additionalContext&&(h=e.additionalContext[a.string]),e&&!1===e.useGlobalScope||(h=h||d[a.string])):"string"==a.type?h="":"atom"==a.type?h=1:"function"==a.type&&(null==d.jQuery||"$"!=a.string&&"jQuery"!=a.string||"function"!=typeof d.jQuery?null!=d._&&"_"==a.string&&"function"==typeof d._&&(h=d._()):h=d.jQuery());for(;null!=h&&b.length;)h=h[b.pop().string];null!=h&&g(h)}else{for(b=a.state.localVars;b;b=b.next)f(b.name);for(b=a.state.globalVars;b;b=
b.next)f(b.name);e&&!1===e.useGlobalScope||g(d);m(k,f)}return c}var n=d.Pos;d.registerHelper("hint","javascript",function(a,b){return p(a,w,function(a,b){return a.getTokenAt(b)},b)});d.registerHelper("hint","coffeescript",function(a,b){return p(a,x,r,b)});var t="charAt charCodeAt indexOf lastIndexOf substring substr slice trim trimLeft trimRight toUpperCase toLowerCase split concat match replace search".split(" "),u="length concat join splice push pop shift unshift slice reverse sort indexOf lastIndexOf every some filter forEach map reduce reduceRight ".split(" "),
v=["prototype","apply","call","bind"],w="break case catch continue debugger default delete do else false finally for function if in instanceof new null return switch throw true try typeof var void while with".split(" "),x="and break catch class continue delete do else extends false finally for if in instanceof isnt new no not null of off on or return switch then throw true try typeof until void while with yes".split(" ")});