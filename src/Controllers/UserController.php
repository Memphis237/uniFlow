<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/userModel.php';
require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../Core/currentUser.php';

class UserController
{
    public function showProfile(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $errors = [];
        $dbError = null;
        $user = null;
        $editing = ($_GET['edit'] ?? '') === '1';

        try {
            $model = new UserModel(db());
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $editing = true;
                $errors = $this->updateProfile($model);
            }
            $user = $model->findById(current_user_id());
            if ($user === null) {
                http_response_code(404);
                $dbError = 'Aucun profil ne correspond à cet identifiant. Vérifiez DB_PROFILE_USER_ID et la table Users.';
            }
        } catch (PDOException $exception) {
            error_log('Unable to load the profile: ' . $exception->getMessage());
            http_response_code(503);
            $dbError = 'La base de données est indisponible ou son schéma n’est pas à jour. Configurez .env puis appliquez le schéma pour une nouvelle base, ou la migration du profil pour une base existante.';
        } catch (RuntimeException $exception) {
            error_log('Invalid profile configuration: ' . $exception->getMessage());
            http_response_code(500);
            $dbError = 'DB_PROFILE_USER_ID doit être un entier positif.';
        }

        if ($errors !== []) {
            $user = array_merge($user ?? [], $this->submittedProfile());
        }

        $success = $_SESSION['profile_success'] ?? null;
        unset($_SESSION['profile_success']);
        $csrfToken = $this->csrfToken();
        require __DIR__ . '/../Views/pages/profile.php';
    }

    private function updateProfile(UserModel $model): array
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($this->csrfToken(), $token)) {
            return ['Votre session a expiré. Rechargez la page avant de réessayer.'];
        }

        $profile = $this->submittedProfile();
        $errors = [];
        foreach (['nom' => 'Le nom', 'prenom' => 'Le prénom'] as $field => $label) {
            if ($profile[$field] === '') {
                $errors[] = $label . ' est obligatoire.';
            } elseif (mb_strlen($profile[$field]) > 255) {
                $errors[] = $label . ' ne peut pas dépasser 255 caractères.';
            }
        }

        if (!filter_var($profile['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($profile['email']) > 255) {
            $errors[] = 'Saisissez une adresse e-mail valide (255 caractères maximum).';
        }
        foreach (['formation' => 'La formation', 'ecole' => 'L’école', 'bio' => 'La biographie'] as $field => $label) {
            $maximum = $field === 'bio' ? 1000 : 255;
            if (mb_strlen($profile[$field]) > $maximum) {
                $errors[] = $label . ' ne peut pas dépasser ' . $maximum . ' caractères.';
            }
        }
        if ($profile['avatar'] !== '') {
            $avatarUrl = filter_var($profile['avatar'], FILTER_VALIDATE_URL);
            $scheme = is_string($avatarUrl) ? strtolower((string) parse_url($avatarUrl, PHP_URL_SCHEME)) : '';
            if (!in_array($scheme, ['http', 'https'], true) || mb_strlen($profile['avatar']) > 255) {
                $errors[] = 'L’avatar doit être une URL HTTP ou HTTPS valide de 255 caractères maximum.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        try {
            $model->updateProfile(current_user_id(), $profile);
            $_SESSION['profile_success'] = 'Votre profil a été mis à jour.';
            header('Location: ?action=profile');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return ['Cette adresse e-mail est déjà utilisée par un autre compte.'];
            }
            error_log('Unable to update the profile: ' . $exception->getMessage());
            return ['La mise à jour a échoué à cause d’une erreur de base de données. Vérifiez la configuration et le schéma.'];
        }
    }

    private function submittedProfile(): array
    {
        $fields = ['nom', 'prenom', 'email', 'formation', 'ecole', 'bio', 'avatar'];
        $profile = [];
        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            $profile[$field] = is_string($value) ? trim($value) : '';
        }
        return $profile;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
