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
  var name = name || null;
  if(!name) return this.data.options;
  return this.data.options[name] || [];
}

/**
 * Return option by id
 */
_RockFinder2.prototype.getOption = function(name, id) {
  return this.getOptions(name)[id];
}

_RockFinder2.prototype.getRelation = function(name) {
  return this.data.relations[name];
}

/**
 * Get relation data
 */
_RockFinder2.prototype.getRelationData = function(relation, field, value) {
  var relationData = this.getRelation(relation).data;
  var result = [];
  for(var i=0; i<relationData.length; i++) {
    var item = relationData[i];
    if(item[field] == value) result.push(item);
  }
  return result;
}
