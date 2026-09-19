      </div>

      <footer class="portal-auth-footer app-auth-footer">
        <span>© <?php echo date('Y'); ?> <?php echo html_escape($title_name); ?></span>
        <span class="portal-auth-footer__dot" aria-hidden="true"></span>
        <a href="mailto:support@qubextrack.com">Support</a>
      </footer>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo base_url('backend/js/hospital-auth-toast.js'); ?>"></script>
<?php if (!empty($register_footer_scripts)) { echo $register_footer_scripts; } ?>
</body>
</html>
