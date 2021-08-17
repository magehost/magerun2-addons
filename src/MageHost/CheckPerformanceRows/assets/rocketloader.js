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
    console.log('Usage: rocketloader.js <some URL>');
    phantom.exit(1);
} else {
    address = system.args[1];

    page.onResourceRequested = function (req) {
        var suffix = 'rocket-loader.min.js';
        if (req.url.indexOf(suffix, req.url.length - suffix.length) !== -1) {
            console.log('Rocketloader found\n');
            phantom.exit(0);
        }
    };

    page.open(address, function (status) {
        if (status !== 'success') {
            require('system').stderr.write('Failed to load the address\n');
            phantom.exit(1);
        } else {
            require('system').stderr.write('No rocketloader found\n');
            phantom.exit(2);
        }
    });
}