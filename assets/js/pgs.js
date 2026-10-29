// Alias to admin/js/lib/pgs.js
var PGS = PGS || (typeof window !== 'undefined' ? window.PGS : undefined);
if (!PGS) {
  // If not yet loaded, include the same definition
  document.write('<script src="admin/js/lib/pgs.js"><\/script>');
}
