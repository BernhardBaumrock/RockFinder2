/**
 * RockFinder2 JavaScript Object
 */
function _RockFinder2(json) {
  // global config
  this.conf = {};

  this.data = {};
  if(json) this.data = JSON.parse(json);
};

/**
 * Return options for given field
 */
_RockFinder2.prototype.getOptions = function(name) {
  return this.data.options[name];
}

/**
 * Return option by id
 */
_RockFinder2.prototype.getOption = function(name, id) {
  return this.getOptions(name)[id];
}
