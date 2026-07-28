'use strict';

const assert = require('node:assert/strict');
const { escapeHtml, fillInText, fillInAnswerString } = require('../../js/common.js');

assert.equal(
    escapeHtml('<img src=x onerror="alert(1)">\''),
    '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;&#039;'
);

const maliciousWords = [
    {
        before: '<svg onload=alert(1)>',
        word: '<script>alert(2)</script>',
        after: '&"\'',
        shouldBeBlanked: true
    }
];

const boldOutput = fillInText(maliciousWords, true);
assert.equal(
    boldOutput,
    '&lt;svg onload=alert(1)&gt;<strong>&lt;script&gt;alert(2)&lt;/script&gt;</strong>&amp;&quot;&#039;'
);
assert.doesNotMatch(boldOutput, /<script|<svg/i);

const inputOutput = fillInText(maliciousWords, false);
assert.match(inputOutput, /class="fill-in-blank-input"/);
assert.doesNotMatch(inputOutput, /<script|<svg/i);

// This helper intentionally returns plain text; callers insert it through a
// DOM text node instead of interpreting it as HTML.
assert.equal(fillInAnswerString(maliciousWords), '<script>alert(2)</script>');

console.log('common.js security tests passed');
