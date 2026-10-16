<?php
// Página pública para restablecer contraseña mediante token
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Restablecer contraseña</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div id="app" class="min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md bg-white rounded-2xl p-8 shadow">
            <h2 class="text-2xl font-bold mb-4">Restablecer contraseña</h2>
            <p class="text-sm text-gray-600 mb-6">Ingrese su nueva contraseña para la cuenta vinculada al token.</p>

            <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded">{{ error }}</div>
            <div v-if="success" class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded">{{ success }}</div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nueva contraseña</label>
                    <input v-model="password" type="password" required class="mt-1 block w-full rounded border-gray-300 shadow-sm" placeholder="mínimo 6 caracteres" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                    <input v-model="confirm" type="password" required class="mt-1 block w-full rounded border-gray-300 shadow-sm" />
                </div>

                <div class="flex items-center justify-between">
                    <button :disabled="loading" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">Restablecer contraseña</button>
                    <a href="./login.html" class="text-sm text-gray-600 hover:underline">Volver al login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        createApp({
            data() { return { token: '<?php echo htmlspecialchars($token, ENT_QUOTES); ?>', password: '', confirm: '', loading: false, error: '', success: '' }},
            methods: {
                async submit() {
                    this.error = '';
                    this.success = '';
                    if (!this.token) return this.error = 'Token inválido.';
                    if (this.password.length < 6) return this.error = 'La contraseña debe tener al menos 6 caracteres.';
                    if (this.password !== this.confirm) return this.error = 'Las contraseñas no coinciden.';
                    this.loading = true;
                    try {
                        const res = await fetch('./backend/api/reset_password.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ token: this.token, password: this.password })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.success = data.message || 'Contraseña restablecida correctamente.';
                        } else {
                            this.error = data.message || 'Error al restablecer contraseña.';
                        }
                    } catch (e) {
                        this.error = 'Error de conexión.';
                    } finally { this.loading = false; }
                }
            }
        }).mount('#app');
    </script>
</body>
</html>
