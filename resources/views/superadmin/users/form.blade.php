<x-layouts.admin :title="$account->exists ? 'Edit User' : 'Tambah User'">
    <style>
        .account-card {
            max-width: 720px;
            margin: auto;
            padding: 30px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 2px 7px #0002;
        }
        .account-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .account-field { display: block; }
        .account-field span {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .account-field input,
        .account-field select,
        .account-field textarea {
            box-sizing: border-box;
            width: 100%;
            border: 1px solid #9ca3af;
            border-radius: 7px;
            padding: 10px;
            background: #fff;
            font: inherit;
        }
        .account-password-control { display: flex; align-items: center; gap: 8px; }
        .account-password-control input { min-width: 0; flex: 1; }
        .account-password-toggle {
            display: grid;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            place-items: center;
            border: 1px solid #9ca3af;
            border-radius: 7px;
            background: #fff;
            color: #111827;
            cursor: pointer;
        }
        .account-password-toggle svg { width: 20px; height: 20px; }
        .account-field.full { grid-column: 1 / -1; }
        .account-help {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }
        .account-error {
            margin: 0 0 18px;
            color: #dc2626;
        }
        .account-save {
            margin-top: 22px;
            border: 0;
            border-radius: 7px;
            padding: 11px 22px;
            background: #2653ff;
            color: #fff;
            font-weight: 600;
        }
        @media (max-width: 650px) {
            .account-grid { grid-template-columns: 1fr; }
        }
    </style>

    <form
        method="POST"
        action="{{ $account->exists ? route('superadmin.users.update', $account) : route('superadmin.users.store') }}"
        class="account-card"
    >
        @csrf
        @if($account->exists)
            @method('PUT')
        @endif

        <h1>{{ $account->exists ? 'Edit User' : 'Tambah User' }}</h1>

        @if($errors->any())
            <p class="account-error">{{ $errors->first() }}</p>
        @endif

        <div class="account-grid">
            <label class="account-field full">
                <span>Tipe Akun Admin</span>
                <select name="role" required>
                    @foreach($roleLabels as $role => $label)
                        <option value="{{ $role }}" @selected(old('role', $account->role ?: 'admin') === $role)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <small class="account-help">
                    Admin TJSL mengakses dashboard pengelolaan Program dan Bantuan TJSL. Admin PUMK mengakses dashboard Kartu Piutang PUMK. Super Admin mengelola seluruh akun dan persetujuan.
                </small>
            </label>

            @foreach([
                'nama_depan' => 'Nama Depan',
                'nama_belakang' => 'Nama Belakang',
                'username' => 'Username',
                'email' => 'Email',
                'jabatan' => 'Jabatan',
                'no_telephone' => 'No Telephone',
            ] as $name => $label)
                <label class="account-field">
                    <span>{{ $label }}</span>
                    <input
                        name="{{ $name }}"
                        value="{{ old($name, $account->$name) }}"
                        @required(in_array($name, ['nama_depan', 'nama_belakang', 'username', 'email'], true))
                    >
                </label>
            @endforeach

            <label class="account-field full">
                <span>Alamat</span>
                <textarea name="alamat">{{ old('alamat', $account->alamat) }}</textarea>
            </label>

            @unless($account->exists)
                <label class="account-field full">
                    <span>Password Sementara</span>
                    <span class="account-password-control">
                        <input type="password" name="password" required minlength="8" autocomplete="new-password">
                        <button type="button" class="account-password-toggle" data-password-toggle aria-label="Lihat password sementara" title="Lihat password sementara" aria-pressed="false">
                            <svg data-password-eye viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                            <svg data-password-eye-off viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.9 10.9 0 0 1 12 5c6 0 9.5 7 9.5 7a16 16 0 0 1-3.2 4.1M6.6 6.6C4.4 8.1 2.9 10.1 2 12c0 0 3.5 7 10 7 1 0 2-.2 2.9-.6"/></svg>
                        </button>
                    </span>
                </label>
            @endunless
        </div>

        <button class="account-save" type="submit">Simpan</button>
    </form>
    <script>
        document.querySelectorAll('[data-password-toggle]').forEach(function(button){
            button.addEventListener('click',function(){
                var input=button.parentElement.querySelector('input');
                var isVisible=input.type==='text';
                input.type=isVisible?'password':'text';
                button.querySelector('[data-password-eye]').hidden=!isVisible;
                button.querySelector('[data-password-eye-off]').hidden=isVisible;
                var label=isVisible?'Lihat password sementara':'Sembunyikan password sementara';
                button.setAttribute('aria-label',label);
                button.setAttribute('title',label);
                button.setAttribute('aria-pressed',String(!isVisible));
            });
        });
    </script>
</x-layouts.admin>
