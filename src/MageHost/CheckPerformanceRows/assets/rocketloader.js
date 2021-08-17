#!/usr/bin/nodejs

'use strict';
var page = require('webpage').create(),
    system = require('system'),
    t, address;

if (system.args.length === 1) {
    console.log('Usage: pageload.js <some URL>');
    phantom.exit(1);
} else {
    address = system.args[1];
    page.onResourceRequested = function (req) {
        if (req.url.endsWith('rocket-loader.min.js')) {
            console.log('Rocketloader found\n');
            phantom.exit(0);
        }
    };

    page.open(address, function (status) {
        if (status !== 'success') {
            require('system').stderr.write('Failed to load the address\n');
            phantom.exit(1);
        }
    });


    require('system').stderr.write('Rocketloader not found\n');
    phantom.exit(2);
}