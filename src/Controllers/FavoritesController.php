<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/../Models/favoritesModel.php';
require_once __DIR__ . '/../Core/currentUser.php';

final class FavoritesController
{
    public function services(): void
    {
        $this->renderPage(false);
    }

    public function favorites(): void
    {
        $this->renderPage(true);
    }

    public function mutate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Méthode non autorisée.';
            return;
        }

        $returnTo = ($_POST['return_to'] ?? '') === 'favorites' ? 'favorites' : 'services';
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $_SESSION['favorites_error'] = 'Votre session a expiré. Rechargez la page avant de réessayer.';
            header('Location: ?action=' . $returnTo, true, 303);
            return;
        }

        $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($serviceId === false) {
            $_SESSION['favorites_error'] = 'Le service sélectionné est invalide.';
            header('Location: ?action=' . $returnTo, true, 303);
            return;
        }

        try {
            $model = new FavoritesModel(db());
            $userId = current_user_id();
            if (($_POST['favorite_action'] ?? '') === 'add') {
                if ($model->add($userId, $serviceId)) {
                    $_SESSION['favorites_success'] = 'Service ajouté à tes favoris.';
                } else {
                    $_SESSION['favorites_error'] = 'Ce service n’existe plus, n’est pas disponible ou est déjà dans tes favoris.';
                }
            } elseif (($_POST['favorite_action'] ?? '') === 'remove') {
                if ($model->remove($userId, $serviceId)) {
                    $_SESSION['favorites_success'] = 'Service retiré de tes favoris.';
                } else {
                    $_SESSION['favorites_error'] = 'Ce service ne figurait pas dans tes favoris.';
                }
            } else {
                $_SESSION['favorites_error'] = 'Action non reconnue.';
            }
        } catch (PDOException $exception) {
            error_log('Unable to update favorites: ' . $exception->getMessage());
            $_SESSION['favorites_error'] = 'La mise à jour des favoris a échoué. Vérifie la connexion à la base de données.';
        } catch (RuntimeException $exception) {
            error_log('Invalid favorites configuration: ' . $exception->getMessage());
            $_SESSION['favorites_error'] = 'La configuration de l’utilisateur est invalide.';
        }

        header('Location: ?action=' . $returnTo, true, 303);
    }

    public function createService(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Méthode non autorisée.';
            return;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $_SESSION['service_form_error'] = 'Votre session a expiré. Recharge la page avant de réessayer.';
            header('Location: ?action=services&add=1', true, 303);
            return;
        }

        $service = $this->submittedService();
        [$upload, $uploadErrors] = $this->validatePhotoUpload();
        $errors = array_merge($this->validateService($service, $upload !== null), $uploadErrors);
        if ($errors !== []) {
            $_SESSION['service_form_errors'] = $errors;
            $_SESSION['service_form_values'] = $service;
            header('Location: ?action=services&add=1', true, 303);
            return;
        }

        try {
            $userId = current_user_id();
        } catch (RuntimeException $exception) {
            error_log('Invalid service owner configuration: ' . $exception->getMessage());
            $_SESSION['service_form_error'] = 'La configuration de l’utilisateur est invalide.';
            $_SESSION['service_form_values'] = $service;
            header('Location: ?action=services&add=1', true, 303);
            return;
        }

        $storedPhotoPath = null;
        $submittedPhotoUrl = $service['photo_url'];
        try {
            if ($upload !== null) {
                $storedPhotoPath = $this->storePhotoUpload($upload);
                $service['photo_url'] = $storedPhotoPath;
            }
            $model = new FavoritesModel(db());
            $model->createService($userId, $service);
            $_SESSION['favorites_success'] = 'Ton service a été publié dans le catalogue.';
            header('Location: ?action=services', true, 303);
            return;
        } catch (InvalidArgumentException $exception) {
            $this->removeStoredPhoto($storedPhotoPath);
            $_SESSION['service_form_errors'] = ['Choisis une catégorie existante.'];
            $service['photo_url'] = $submittedPhotoUrl;
            $_SESSION['service_form_values'] = $service;
            header('Location: ?action=services&add=1', true, 303);
            return;
        } catch (PDOException $exception) {
            $this->removeStoredPhoto($storedPhotoPath);
            error_log('Unable to create service: ' . $exception->getMessage());
            $_SESSION['service_form_error'] = 'La publication du service a échoué. Vérifie le schéma de la base de données.';
            $service['photo_url'] = $submittedPhotoUrl;
            $_SESSION['service_form_values'] = $service;
        } catch (RuntimeException $exception) {
            $this->removeStoredPhoto($storedPhotoPath);
            error_log('Unable to store service photo: ' . $exception->getMessage());
            $_SESSION['service_form_error'] = 'La photo n’a pas pu être enregistrée. Vérifie les permissions du dossier public/uploads/services.';
            $service['photo_url'] = $submittedPhotoUrl;
            $_SESSION['service_form_values'] = $service;
        }

        header('Location: ?action=services&add=1', true, 303);
    }

    public function deleteService(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $returnTo = ($_POST['return_to'] ?? '') === 'modern' ? 'page=services' : 'action=services';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            echo 'Méthode non autorisée.';
            return;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            $_SESSION['favorites_error'] = 'Votre session a expiré. Recharge la page avant de réessayer.';
            header('Location: ?' . $returnTo, true, 303);
            return;
        }

        $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($serviceId === false) {
            $_SESSION['favorites_error'] = 'Le service sélectionné est invalide.';
            header('Location: ?' . $returnTo, true, 303);
            return;
        }

        try {
            $deleted = (new FavoritesModel(db()))->deleteOwnedService(current_user_id(), $serviceId);
            if ($deleted === false) {
                $_SESSION['favorites_error'] = 'Tu ne peux supprimer que tes propres services.';
            } else {
                foreach ($deleted['photo_paths'] ?? [] as $photoPath) {
                    $this->removeStoredPhoto($photoPath);
                }
                $_SESSION['favorites_success'] = 'Ton service a été supprimé.';
            }
        } catch (PDOException $exception) {
            error_log('Unable to delete service: ' . $exception->getMessage());
            $_SESSION['favorites_error'] = $exception->getCode() === '23000'
                ? 'Ce service ne peut pas être supprimé car il est lié à une réservation ou à un avis.'
                : 'La suppression a échoué à cause d’une erreur de base de données.';
        } catch (RuntimeException $exception) {
            error_log('Invalid service owner configuration: ' . $exception->getMessage());
            $_SESSION['favorites_error'] = 'La configuration de l’utilisateur est invalide.';
        }

        header('Location: ?' . $returnTo, true, 303);
    }

    private function renderPage(bool $onlyFavorites): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $success = $_SESSION['favorites_success'] ?? null;
        $error = $_SESSION['favorites_error'] ?? null;
        $serviceFormError = $_SESSION['service_form_error'] ?? null;
        $serviceFormErrors = $_SESSION['service_form_errors'] ?? [];
        $serviceForm = $_SESSION['service_form_values'] ?? [];
        unset(
            $_SESSION['favorites_success'],
            $_SESSION['favorites_error'],
            $_SESSION['service_form_error'],
            $_SESSION['service_form_errors'],
            $_SESSION['service_form_values']
        );
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_token'];
        $dbError = null;
        $services = [];
        $categories = [];
        $userId = null;

        try {
            $userId = current_user_id();
            $model = new FavoritesModel(db());
            $services = $onlyFavorites ? $model->listFavorites($userId) : $model->listServices($userId);
            if (!$onlyFavorites) {
                $categories = $model->listCategories();
            }
        } catch (PDOException $exception) {
            error_log('Unable to load favorites and services: ' . $exception->getMessage());
            http_response_code(503);
            $dbError = 'Le catalogue ou la base de données est indisponible. Vérifie le schéma UniFlow.';
        } catch (RuntimeException $exception) {
            error_log('Invalid favorites configuration: ' . $exception->getMessage());
            http_response_code(500);
            $dbError = 'La configuration de l’utilisateur est invalide.';
        }

        $pageTitle = $onlyFavorites ? 'Mes favoris' : 'Tous les services';
        $currentAction = $onlyFavorites ? 'favorites' : 'services';
        $showCreateForm = !$onlyFavorites && (($_GET['add'] ?? '') === '1' || $serviceFormErrors !== [] || $serviceFormError !== null);
        require __DIR__ . '/../Views/pages/legacyServices.php';
    }

    private function submittedService(): array
    {
        $fields = ['title', 'price', 'location', 'description', 'photo_url'];
        $service = [];
        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            $service[$field] = is_string($value) ? trim($value) : '';
        }
        $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $service['category_id'] = $categoryId === false ? 0 : $categoryId;
        return $service;
    }

    private function validateService(array $service, bool $hasUploadedPhoto): array
    {
        $errors = [];
        foreach (['title' => 'Le titre', 'description' => 'La description', 'location' => 'Le lieu'] as $field => $label) {
            if ($field === 'title' && $service[$field] === '') {
                $errors[] = 'Le titre est obligatoire.';
            } elseif (mb_strlen($service[$field]) > 255) {
                $errors[] = $label . ' ne peut pas dépasser 255 caractères.';
            }
        }
        if ($service['category_id'] < 1) {
            $errors[] = 'Choisis une catégorie.';
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $service['price']) || (float) $service['price'] > 9999999999999.99) {
            $errors[] = 'Saisis un prix valide égal ou supérieur à zéro.';
        }
        if (!$hasUploadedPhoto && $service['photo_url'] !== '') {
            $url = filter_var($service['photo_url'], FILTER_VALIDATE_URL);
            $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';
            if (!in_array($scheme, ['http', 'https'], true) || strlen($service['photo_url']) > 2000) {
                $errors[] = 'Le lien photo doit être une URL HTTP ou HTTPS valide (2000 caractères maximum).';
            }
        }
        return $errors;
    }

    private function validatePhotoUpload(): array
    {
        $file = $_FILES['photo_file'] ?? null;
        if ($file === null) {
            return [null, []];
        }
        if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
            return [null, ['Le fichier envoyé n’est pas une photo valide.']];
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return [null, []];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La photo dépasse la taille maximale autorisée de 5 Mo.',
                UPLOAD_ERR_PARTIAL => 'Le transfert de la photo est incomplet. Réessaie.',
                default => 'Le transfert de la photo a échoué. Réessaie avec un autre fichier.',
            };
            return [null, [$message]];
        }

        if (!isset($file['tmp_name'], $file['size']) || !is_string($file['tmp_name']) || !is_int($file['size'])) {
            return [null, ['Le fichier envoyé n’est pas une photo valide.']];
        }
        if (!is_uploaded_file($file['tmp_name']) || $file['size'] > 5 * 1024 * 1024) {
            return [null, ['La photo doit faire 5 Mo maximum.']];
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $dimensions = @getimagesize($file['tmp_name']);
        if (!isset($extensions[$mime]) || $dimensions === false
            || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] * $dimensions[1] > 40000000) {
            return [null, ['Choisis une vraie image JPG, PNG, WebP ou GIF (40 mégapixels maximum).']];
        }

        return [[
            'tmp_name' => $file['tmp_name'],
            'extension' => $extensions[$mime],
        ], []];
    }

    private function storePhotoUpload(array $upload): string
    {
        $directory = dirname(__DIR__, 2) . '/public/uploads/services';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create service photo directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $upload['extension'];
        if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) {
            throw new RuntimeException('Unable to save uploaded service photo.');
        }
        return '/uploads/services/' . $filename;
    }

    private function removeStoredPhoto(?string $photoPath): void
    {
        if (!is_string($photoPath)
            || !preg_match('~\A/uploads/services/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $photoPath)) {
            return;
        }

        $file = dirname(__DIR__, 2) . '/public' . $photoPath;
        if (is_file($file) && !unlink($file)) {
            error_log('Unable to remove service photo: ' . $file);
        }
    }
}
