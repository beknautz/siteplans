
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- HTMX -->
<script src="https://unpkg.com/htmx.org@1.9.12/dist/htmx.min.js"></script>
<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Leaflet Draw -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<!-- Turf.js for geometry calculations -->
<script src="https://cdn.jsdelivr.net/npm/@turf/turf@6.5.0/turf.min.js"></script>

<?php if (!empty($extra_scripts)): ?>
  <?= $extra_scripts ?>
<?php endif; ?>

<footer class="bg-dark text-secondary text-center py-2 mt-auto" style="font-size:.75rem;">
  <?= COMPANY ?> &copy; <?= date('Y') ?> &mdash; Site Plan Tool v<?= APP_VERSION ?>
</footer>
</body>
</html>
