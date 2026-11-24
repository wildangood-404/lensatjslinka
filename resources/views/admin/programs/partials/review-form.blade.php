<form class="program-review" method="POST" action="{{ $action }}">
    @csrf
    <textarea name="catatan" placeholder="Catatan revisi/penolakan (wajib untuk keputusan tersebut)"></textarea>
    <div class="program-review-buttons">
        <button class="approve" name="decision" value="approve">Disetujui</button>
        <button class="revise" name="decision" value="revise">Direvisi</button>
        @if($allowReject)
            <button class="reject" name="decision" value="reject">Ditolak</button>
        @endif
    </div>
</form>
<script>
    document.currentScript.previousElementSibling.querySelectorAll('button[value="approve"]').forEach((button) => {
        button.addEventListener('click', () => {
            const note = button.form.querySelector('[name="catatan"]');
            note.required = false;
        });
    });
    document.currentScript.previousElementSibling.querySelectorAll('button[value="revise"],button[value="reject"]').forEach((button) => {
        button.addEventListener('click', () => {
            button.form.querySelector('[name="catatan"]').required = true;
        });
    });
</script>
