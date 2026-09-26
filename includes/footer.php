</main>

<footer class="main-footer">
    <p>&copy; <?= date('Y') ?> CCIS Connect. All rights reserved.</p>
</footer>

<script>
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const group = document.createElement('div');
        group.className = 'password-input-group';
        input.parentNode.insertBefore(group, input);
        group.appendChild(input);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-visibility-toggle';
        toggle.textContent = 'Show';
        toggle.setAttribute('aria-label', 'Show password');
        toggle.setAttribute('aria-pressed', 'false');
        group.appendChild(toggle);

        toggle.addEventListener('click', () => {
            const isVisible = input.type === 'text';
            input.type = isVisible ? 'password' : 'text';
            toggle.textContent = isVisible ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            toggle.setAttribute('aria-pressed', String(!isVisible));
        });
    });
</script>
</body>
</html>
