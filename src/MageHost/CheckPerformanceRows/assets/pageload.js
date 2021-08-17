#!/usr/bin/nodejs

'use strict';
var page = require('webpage').create(),
    system = require('system'),
    t, address;

page.onError = function (msg, trace) {
    var msgStack = ['ERROR: ' + msg];
    if (trace && trace.length) {
        msgStack.push('TRACE:');
        trace.forEach(function (t) {
            msgStack.push(' -> ' + t.file + ': ' + t.line + (t.function ? ' (in function "' + t.function + '")' : ''));
        });
    }
};

if (system.args.length === 1) {
    console.log('Usage: pageload.js <some URL>');
    phantom.exit(1);
} else {
    t = Date.now();
    address = system.args[1];
    page.open(address, function (status) {
        if (status !== 'success') {
            require('system').stderr.write('Failed to load the address\n');
            phantom.exit(1);
        } else {
            console.log(Date.now() - t);
            phantom.exit();
        }
    });
}