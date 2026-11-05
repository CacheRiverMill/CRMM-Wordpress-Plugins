const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(process.env.PLUGIN_ROOT || path.join(__dirname, '../crmm-trim-catalog'), 'assets/js/admin-classification.js'), 'utf8');

// Exercise the actual editor script with controllable jQuery requests and editor state.
function editor({ assigned = '', field = assigned, category = '4', subtype = 'CR', title = assigned } = {}) {
 const values = { category, subtype, number: field, title, target: '' };
 const requests = [], handlers = {}, edits = [];
 const config = { assignedNumber: assigned, assignedLabel: 'Permanent part number:', previewLabel: 'Expected next number (not reserved):', postId: 309, categoryCodes: { 4: '4', 1: '1' }, subtypes: { CR: { code: 'CR', category: '4' }, FP: { code: 'FP', category: '4' }, CM: { code: 'CM', category: '1' } } };
 function control(key) {
  return {
   length: 1,
   val(value) { if (arguments.length) { values[key] = value; return this; } return values[key]; },
   text(value) { values[key] = value; return this; },
   trigger(event) { if (event === 'change' && handlers[key]) handlers[key](); return this; },
   find() { return { each(fn) { for (const value of Object.keys(config.subtypes)) fn.call({ value }); } }; },
  };
 }
 const document = {};
 function $(selector) {
  if (typeof selector === 'function') { selector(); return; }
  if (selector === document) return { on(event, selector, fn) { handlers[selector.includes('trim_category') ? 'category' : selector.includes('trim_subtype') ? 'subtype' : 'assign'] = fn; } };
  const key = selector.includes('trim_category') ? 'category' : selector.includes('trim_subtype') ? 'subtype' : selector.includes('part_number') ? 'number' : selector === '#title' ? 'title' : 'target';
  return control(key);
 }
 $.post = (url, data) => {
  const request = { data, aborted: false, done(fn) { this.success = fn; return this; }, fail(fn) { this.failure = fn; return this; }, abort() { this.aborted = true; this.failure?.({}, 'abort'); }, resolve(number, assigned = false) { this.success({ success: true, data: { part_number: number, assigned } }); } };
  requests.push(request); return request;
 };
 const wp = { data: { select() { return { getEditedPostAttribute() { return values.title; } }; }, dispatch() { return { editPost({ title }) { values.title = title; edits.push(title); } }; } } };
 vm.runInNewContext(source, { jQuery: $, window: { CRMMTrimAdmin: config, wp }, wp, document });
 return { values, requests, config, edits, change(key, value) { values[key] = value; handlers[key](); } };
}

test('existing registry assignment wins over a stale field/title without requesting a preview', () => {
 const e = editor({ assigned: '4-CR-1009', field: '4-CR-1015', title: '4-CR-1015' });
 assert.equal(e.requests.length, 0);
 assert.equal(e.values.number, '4-CR-1009');
 assert.equal(e.values.title, '4-CR-1009');
 e.change('subtype', 'FP');
 assert.equal(e.requests.length, 0);
 assert.equal(e.values.title, '4-CR-1009');
});
test('opening a correctly numbered profile does not dirty its title', () => {
 const e = editor({ assigned: '4-CR-1009' });
 assert.deepEqual(e.edits, []);
});
test('new profiles show an explicitly unreserved preview without assigning a field or title', () => {
 const e = editor();
 e.requests[0].resolve('4-CR-1015');
 assert.equal(e.values.target, 'Expected next number (not reserved): 4-CR-1015');
 assert.equal(e.values.number, '');
 assert.equal(e.values.title, '');
});
test('missing or incompatible new classifications do not request previews', () => {
 for (const options of [{ category: '' }, { subtype: '' }, { category: '1', subtype: 'CR' }]) {
  const e = editor(options);
  assert.equal(e.requests.length, 0);
  assert.equal(e.values.number, '');
 }
});
test('out-of-order success and failure callbacks cannot replace the newest preview', () => {
 const e = editor(); const old = e.requests[0];
 e.change('subtype', 'FP'); const latest = e.requests.at(-1);
 latest.resolve('4-FP-1022');
 old.resolve('4-CR-1015'); old.failure({}, 'error');
 assert.equal(old.aborted, true);
 assert.equal(e.values.target, 'Expected next number (not reserved): 4-FP-1022');
});
test('clearing a classification invalidates an outstanding response', () => {
 const e = editor(); const old = e.requests[0];
 e.change('category', ''); old.resolve('4-CR-1015');
 assert.match(e.values.target, /Select a category/);
 assert.equal(e.values.number, '');
});
test('a newly returned permanent assignment replaces the preview and blocks future previews', () => {
 const e = editor(); e.requests[0].resolve('4-CR-1009', true);
 assert.equal(e.values.title, '4-CR-1009');
 assert.equal(e.values.number, '4-CR-1009');
 e.change('subtype', 'FP');
 assert.equal(e.requests.length, 1);
});
test('an outstanding preview cannot overwrite an assignment received while saving', () => {
 const e = editor(); const old = e.requests[0];
 e.values.number = '4-CR-1009'; old.resolve('4-CR-1015');
 assert.equal(e.values.title, '4-CR-1009');
 assert.equal(e.values.number, '4-CR-1009');
});
