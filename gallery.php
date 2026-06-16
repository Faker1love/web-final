<?php include 'header.php'; ?>

<h2>Галерея нашей студии</h2>
<p>Посмотрите на наше оборудование и акустические решения, которые помогают достигать профессионального звучания.</p>

<style>
    .gallery {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .gallery-item {
        background: #2a2a2a;
        padding: 10px;
        border-radius: 5px;
        text-align: center;
    }
    .gallery-item img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 3px;
        margin-bottom: 10px;
    }
    .gallery-item p {
        font-size: 14px;
        color: #ccc;
    }
</style>

<div class="gallery">
    
    <div class="gallery-item">
        <img src="rooma1.jpg" alt="Студия A">
        <p>Студия A — Основная комната записи</p>
    </div>
    <div class="gallery-item">
        <img src="pult.jpg" alt="Микшерный пульт">
        <p>Аналоговый микшерный пульт для сведения</p>
    </div>
    <div class="gallery-item">
        <img src="mic.jpg" alt="Микрофоны">
        <p>Парк микрофонов для вокала и инструментов</p>
    </div>
    <div class="gallery-item">
        <img src="roombm.jpg" alt="Студия B">
        <p>Студия B — Мастеринг-кабинет</p>
    </div>
</div>

<?php include 'footer.php'; ?>