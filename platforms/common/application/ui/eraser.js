"use strict";
var prime     = require('prime'),
    $         = require('../utils/elements.utils'),
    Emitter   = require('prime/emitter'),
    Bound     = require('prime-util/prime/bound'),
    Options   = require('prime-util/prime/options');


var Eraser = new prime({
    mixin: [Options, Bound],

    inherits: Emitter,

    constructor: function(element, options){
        this.setOptions(options);

        this.element = $(element);

        if (!this.element) { return; }

        this.hide(true);
    },

    setTop: function() {
        if (typeof this.top !== 'undefined') { return; }
        this.top = parseInt(this.element.compute('top'), 10);
    },

    show: function(fast){
        if (!this.element) { return; }
        this.setTop();
        this.out();
        this.element[fast ? 'style' : 'animate']({top: this.top}, {duration: '150ms'});
    },

    hide: function(fast){
        if (!this.element) { return; }
        this.setTop();
        this.element.style('display', 'block');
        var top = {top: -(this.element[0].offsetHeight)};
        this.out();
        this.element[fast ? 'style' : 'animate'](top, {duration: '150ms'});
    },

    over: function(){
        this.element.find('.trash-zone').animate({transform: 'scale(1.2)'}, {duration: '150ms', equation: 'cubic-bezier(0.5,0,0.5,1)'});
    },

    out: function(){
        this.element.find('.trash-zone').animate({transform: 'scale(1)'}, {duration: '150ms', equation: 'cubic-bezier(0.5,0,0.5,1)'});
    }
});

module.exports = Eraser;
