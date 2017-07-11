/*
 * Number.isInteger()
 */
Number.isInteger = Number.isInteger || function(value) {
  return typeof value === 'number' && 
    isFinite(value) && 
    Math.floor(value) === value;
};

/*
 * Event option passive
 */
// window.eventPassiveSupported = false;
// try {
//   let options = Object.defineProperty({}, "passive", {
//     get: function() {
//       window.eventPassiveSupported = true;
//     }
//   });
//   window.addEventListener("test", null, options);
// } catch (e) {}

/*
 * String.prototype.startsWith()
 */
if (!String.prototype.startsWith) {
    String.prototype.startsWith = function(searchString, position){
      return this.substr(position || 0, searchString.length) === searchString;
  };
}
