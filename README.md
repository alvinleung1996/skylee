# \<skylee\>



## Install the Polymer-CLI

First, make sure you have the [Polymer CLI](https://www.npmjs.com/package/polymer-cli) installed. Then run `polymer serve` to serve your application locally.

## Viewing Your Application

```
$ polymer serve
```

## Building Your Application

```
$ polymer build
```

This will create builds of your application in the `build/` directory, optimized to be served in production. You can then serve the built versions by giving `polymer serve` a folder to serve from:

```
$ polymer serve build/default
```

## Running Tests

```
$ polymer test
```

Your application is already set up to be tested via [web-component-tester](https://github.com/Polymer/web-component-tester). Run `polymer test` to run your application's test suite locally.

## Test for IE11 and Edge

```
console.log('$0:');
console.log($0);
console.log('light-dom:')
for (let i = 0; i < $0.children.length; ++i) {
console.log($0.children[i]);
}
console.log('shadow-dom:');
if ($0.shadowRoot) {
for (let i = 0; i < $0.shadowRoot.children.length; ++i) {
console.log($0.shadowRoot.children[i]);
}}
```
