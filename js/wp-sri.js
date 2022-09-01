/**
 * Updates the SRI exclude list using AJAX.
 * @since 0.5.0
 * @author Brennan Goewert <brennan@goewert.me>
 */
document.querySelectorAll('.sri-exclude').forEach(cb => {
    cb.addEventListener( 'change', e => {

        let data = new URLSearchParams({
            action: 'update_sri_exclude',
            url: e.target.getAttribute( 'value' ),
            checked: cb.checked,
            security: sriOptions.security
        });

        fetch(ajaxurl, { method: 'POST', body: data })
            .then(() => {
                let nodeCheckboxNotice = document.createElement('span');
                nodeCheckboxNotice.style.display = 'block';
                nodeCheckboxNotice.innerText = cb.checked ? 'Added' : 'Removed';
            
                e.target.after(nodeCheckboxNotice);
                setTimeout(() => nodeCheckboxNotice.remove(), 300);
            })
            .catch(reason => {
                let nodeExcludeFailed = document.createElement('span');
                nodeExcludeFailed.style.display = 'block';
                nodeExcludeFailed.innerText = 'Failed to exclude.';

                e.target.after(nodeExcludeFailed);
                setTimeout(() => nodeExcludeFailed.remove(), 300);
            });
    });
});