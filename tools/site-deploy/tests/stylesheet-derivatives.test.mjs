import {test} from 'node:test';
import assert from 'node:assert/strict';
import {bodyContextStylesheet, deriveStylesheets} from '../stylesheet-derivatives.mjs';

const context = {required_body_classes: ['home', 'page'], absent_body_classes: ['single-post', 'single-job_posting', 'page-id-97']};

test('Body subsets retain interactive states, descendants, negations and uncertain selectors', () => {
  const retained = [
    '.home .menu:hover', '.home .menu:focus-within', '.home .dialog[open]',
    'body:not(.single-post) p', 'body:has(.single-post) p', 'body .single-post',
    'h1.single-post', ':is(.single-post,.home) p', ':where(.single-post,.unknown) p',
    ':is(.single-post .nested,.single-job_posting) p', '.unknown .single-post',
  ];
  for (const selector of retained) {
    const css = selector + ' { color: red; }';
    assert.equal(bodyContextStylesheet(css, context), css, selector);
  }
  for (const selector of ['.single-post p', 'body.single-post p', '.single-job_posting:hover', ':is(.single-post,.single-job_posting) p', 'body.page-id-97 p']) {
    assert.equal(bodyContextStylesheet(selector + ' { color: red; }', context), '', selector);
  }
});

test('Retained declaration and cascade order survives nested conditions and selector lists', () => {
  const css = '@font-face{font-family:x;src:url(fonts/x.woff2)}\n'
    + ':root{--token:1} .home p{color:red} .single-post p, .home a{color:blue}\n'
    + '@media(max-width:782px){.single-post p{color:black}.home p{color:green}}\n'
    + '@supports(display:grid){.home p{display:grid}}\n'
    + '@keyframes keep{from{opacity:0}to{opacity:1}}';
  const actual = bodyContextStylesheet(css, context);
  assert.equal(actual, css.replace('.single-post p, ', '').replace('.single-post p{color:black}', ''));
  assert.equal(bodyContextStylesheet('.single-post{ & .child {color:red} }', context), '.single-post{ & .child {color:red} }');
});

test('Derived bundles retain source order and relative dependency locations', () => {
  const bodies = new Map([['fonts.css', Buffer.from('@font-face{src:url(fonts/x.woff2)}')], ['style.css', Buffer.from('.single-post{color:red}.home{color:blue}')]]);
  const result = deriveStylesheets(bodies, {
    stylesheet_contexts: {'home-shell.css': context},
    stylesheet_subsets: {'style-home.css': {source:'style.css', context:'home-shell.css'}},
    stylesheet_bundles: {'home-shell.css': ['fonts.css','style-home.css']},
  });
  assert.equal(bodies.get('home-shell.css').toString(), '@font-face{src:url(fonts/x.woff2)}\n.home{color:blue}');
  assert.equal(result.generated.size, 2);
  for (const declaration of [
    {stylesheet_subsets:{'style-home.css':{source:'style.css', context:'missing'}}},
    {stylesheet_bundles:{'style.css':['fonts.css']}},
    {stylesheet_bundles:{'../outside.css':['fonts.css']}},
    {stylesheet_bundles:{'subdir/new.css':['fonts.css']}},
    {stylesheet_contexts:{'missing.css':context}},
    {stylesheet_contexts:{'new.css':{...context,absent_body_classes:['is-open']}}},
    {stylesheet_contexts:{'new.css':{...context,absent_body_classes:['home']}}},
  ]) assert.throws(() => deriveStylesheets(new Map([['fonts.css',Buffer.from('a{}')],['style.css',Buffer.from('b{}')]]), declaration));
  for (const directive of ['@import "other.css";', '@charset "utf-8";']) {
    assert.throws(() => deriveStylesheets(new Map([['style.css',Buffer.from(directive)]]), {stylesheet_bundles:{'new.css':['style.css']}}), /Unsupported/);
  }
});
