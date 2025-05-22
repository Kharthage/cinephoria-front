<?php
include 'includes/header.php';
include '../api_helper.php';
?>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Administration Cinéphoria</h1>

    <div class="row g-4">

        <!-- Films -->
        <div class="col-md-4">
            <a href="Films/index.php" class="text-decoration-none">
                <div class="card text-center shadow h-100">
                    <div class="card-body">
                        <i class="bi bi-film fs-1 text-primary mb-3"></i>
                        <h5 class="card-title">Films</h5>
                    </div>
                </div>
            </a>
        </div>

        <!-- Séances -->
        <div class="col-md-4">
            <a href="Seances/index.php" class="text-decoration-none">
                <div class="card text-center shadow h-100">
                    <div class="card-body">
                        <i class="bi bi-calendar-event fs-1 text-success mb-3"></i>
                        <h5 class="card-title">Séances</h5>
                    </div>
                </div>
            </a>
        </div>

        <!-- Salles -->
        <div class="col-md-4">
            <a href="Salles/index.php" class="text-decoration-none">
                <div class="card text-center shadow h-100">
                    <div class="card-body">
                        <i class="bi bi-door-open fs-1 text-warning mb-3"></i>
                        <h5 class="card-title">Salles</h5>
                    </div>
                </div>
            </a>
        </div>

        <!-- Employés -->
        <div class="col-md-4">
            <a href="Employes/index.php" class="text-decoration-none">
                <div class="card text-center shadow h-100">
                    <div class="card-body">
                        <i class="bi bi-person-badge fs-1 text-danger mb-3"></i>
                        <h5 class="card-title">Employés</h5>
                    </div>
                </div>
            </a>
        </div>

        <!-- Cinémas -->
        <div class="col-md-4">
            <a href="Cinemas/index.php" class="text-decoration-none">
                <div class="card text-center shadow h-100">
                    <div class="card-body">
                        <i class="bi bi-geo-alt fs-1 text-info mb-3"></i>
                        <h5 class="card-title">Cinémas</h5>
                    </div>
                </div>
            </a>
        </div>

    </div>
</div>


<?php include '../includes/footer.php'; ?>