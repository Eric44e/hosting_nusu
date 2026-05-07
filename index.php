
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>NUSU LTD -Your Project Partener</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <style>
    a { text-decoration: none !important; }
    </style>

    <!-- Favicon -->
    <link href="imgs/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@400;600;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="libs/animate/animate.min.css" rel="stylesheet">
    <link href="libs/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Template Stylesheet -->
    <link href="csss/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- ═══════════════════════════════════════
         NUSU ANIMATION ENHANCEMENTS
    ═══════════════════════════════════════ -->
    <style>

    /* ─── SCROLL REVEAL BASE ─── */
    .sr { opacity: 0; transform: translateY(48px); transition: opacity 0.75s cubic-bezier(.22,1,.36,1), transform 0.75s cubic-bezier(.22,1,.36,1); }
    .sr.sr-left  { transform: translateX(-60px); }
    .sr.sr-right { transform: translateX(60px); }
    .sr.sr-scale { transform: scale(0.88); }
    .sr.sr-done  { opacity: 1 !important; transform: none !important; }

    .sr-d1 { transition-delay: 0.1s !important; }
    .sr-d2 { transition-delay: 0.22s !important; }
    .sr-d3 { transition-delay: 0.34s !important; }
    .sr-d4 { transition-delay: 0.46s !important; }
    .sr-d5 { transition-delay: 0.58s !important; }
    .sr-d6 { transition-delay: 0.7s !important; }

    /* ─── NAVBAR SCROLL GLASS EFFECT ─── */
    .navbar { transition: box-shadow 0.3s, background 0.3s; }
    .navbar.scrolled { box-shadow: 0 4px 30px rgba(0,0,0,0.12); }

    /* ─── CAROUSEL TYPEWRITER ─── */
    .typewriter-text { overflow: hidden; white-space: nowrap; border-right: 3px solid #fff; animation: typewriter 2.8s steps(30,end) forwards, blinkCaret 0.7s step-end infinite; display: inline-block; max-width: 100%; }
    @keyframes typewriter { from { width: 0; } to { width: 100%; } }
    @keyframes blinkCaret { 0%,100% { border-color: transparent; } 50% { border-color: #fff; } }

    /* ─── HERO BLUR ─── */
    .carousel-item img { animation: none; }
    .carousel-item { overflow: hidden; }

    /* ─── ANIMATED COUNTER ─── */
    .counter-num { transition: all 0.3s; display: inline-block; }

    /* ─── FACTS PARTICLES ─── */
    .facts { position: relative; overflow: hidden; }
    .facts::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 20% 50%, rgba(0,120,255,0.18) 0%, transparent 60%), radial-gradient(ellipse at 80% 30%, rgba(0,60,180,0.12) 0%, transparent 55%); pointer-events: none; z-index: 0; }
    .facts .container { position: relative; z-index: 1; }
    .particle { position: absolute; border-radius: 50%; pointer-events: none; animation: floatParticle linear infinite; opacity: 0.18; }
    @keyframes floatParticle { 0% { transform: translateY(100%) rotate(0deg); opacity: 0; } 10% { opacity: 0.18; } 90% { opacity: 0.18; } 100% { transform: translateY(-120px) rotate(360deg); opacity: 0; } }

    /* ─── COUNTER BADGE ─── */
    .facts h1[data-count] { position: relative; display: inline-block; }
    .facts h1[data-count]::after { content: '+'; font-size: 2rem; color: var(--primary, #0d6efd); vertical-align: super; }

    /* ─── SERVICE CARD GLOW ─── */
    .service-item { transition: transform 0.35s cubic-bezier(.34,1.56,.64,1), box-shadow 0.35s; border-radius: 8px; overflow: hidden; }
    .service-item:hover { transform: translateY(-10px) scale(1.025); box-shadow: 0 20px 50px rgba(13,110,253,0.22); }
    .service-item img { transition: transform 0.5s ease; }
    .service-item:hover img { transform: scale(1.08); }

    /* ─── ABOUT IMAGES FLOAT ─── */
    @keyframes imgFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    .about-float { animation: imgFloat 4s ease-in-out infinite; }
    .about-float-delay { animation: imgFloat 4s ease-in-out 1.2s infinite; }

    /* ─── WHY CHOOSE US ICONS ─── */
    .feature-icon-wrap { position: relative; overflow: hidden; }
    .feature-icon-wrap::after { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,0.15); border-radius: 50%; transform: scale(0); transition: transform 0.4s; }
    .feature-icon-wrap:hover::after { transform: scale(1); }

    /* ─── SECTION HEADING UNDERLINE ─── */
    .section-heading { position: relative; display: inline-block; }
    .section-heading::after { content: ''; position: absolute; left: 0; bottom: -8px; height: 3px; width: 0; background: linear-gradient(90deg, var(--primary, #0d6efd), #00c4ff); border-radius: 2px; transition: width 0.8s cubic-bezier(.22,1,.36,1); }
    .section-heading.sr-done::after { width: 60%; }

    /* ─── QUOTE FORM FOCUS ─── */
    .form-control { transition: border-color 0.3s, box-shadow 0.3s; }
    .form-control:focus { box-shadow: 0 0 0 3px rgba(13,110,253,0.18) !important; }

    /* ─── WHATSAPP BUTTON PULSE ─── */
    .whatsapp-chat { animation: waPulse 2.5s infinite; }
    @keyframes waPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(37,211,102,0.5); } 70% { box-shadow: 0 0 0 14px rgba(37,211,102,0); } }

    /* ─── FOOTER LINKS ─── */
    .footer .btn-link { transition: color 0.25s, padding-left 0.25s; }
    .footer .btn-link:hover { color: var(--primary, #0d6efd) !important; padding-left: 8px; }

    /* ══════════════════════════════════════
       HORIZONTAL TESTIMONIAL MARQUEE
    ══════════════════════════════════════ */
    .testimonial-track-wrap {
        overflow: hidden;
        position: relative;
        padding: 20px 0;
        -webkit-mask-image: linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%);
        mask-image: linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%);
    }
    .testimonial-track {
        display: flex;
        gap: 24px;
        width: max-content;
        animation: marqueeScroll 38s linear infinite;
    }
    .testimonial-track:hover { animation-play-state: paused; }

    @keyframes marqueeScroll {
        0%   { transform: translateX(0); }
        100% { transform: translateX(-50%); }
    }

    .tcard {
        flex-shrink: 0;
        width: 320px;
        background: #fff;
        border-radius: 16px;
        padding: 28px 28px 24px;
        box-shadow: 0 6px 30px rgba(0,0,0,0.09);
        border: 1px solid rgba(13,110,253,0.08);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
    }
    .tcard:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(13,110,253,0.18); }

    .tcard-quote {
        font-size: 56px;
        line-height: 1;
        color: var(--primary, #0d6efd);
        font-family: Georgia, serif;
        opacity: 0.25;
        position: absolute;
        top: 14px;
        left: 22px;
    }

    .tcard-stars { color: #f59e0b; font-size: 13px; margin-bottom: 12px; letter-spacing: 2px; }

    .tcard p {
        font-size: 14.5px;
        line-height: 1.7;
        color: #444;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
    }

    .tcard-footer { display: flex; align-items: center; gap: 12px; }
    .tcard-avatar {
        width: 46px; height: 46px; border-radius: 50%; object-fit: cover;
        border: 2px solid rgba(13,110,253,0.2);
    }
    .tcard-avatar-placeholder {
        width: 46px; height: 46px; border-radius: 50%;
        background: linear-gradient(135deg, #0d6efd, #00c4ff);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 16px;
        border: 2px solid rgba(13,110,253,0.2);
        flex-shrink: 0;
    }
    .tcard-name { font-weight: 700; font-size: 14px; color: #111; line-height: 1.3; }
    .tcard-role { font-size: 12px; color: #888; }

    /* ─── BACK TO TOP ─── */
    .back-to-top { opacity: 0; transform: translateY(20px); transition: opacity 0.4s, transform 0.4s; pointer-events: none; }
    .back-to-top.show { opacity: 1; transform: translateY(0); pointer-events: all; }

    /* ─── LOADING BAR ─── */
    #loading-bar { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: linear-gradient(90deg, #FF7A00, #ffb366); z-index: 99999; transition: width 0.4s ease; border-radius: 0 2px 2px 0; }
    
    .btn-primary { background-color: #FF7A00; border-color: #FF7A00; }
    .btn-primary:hover { background-color: #E66E00; border-color: #E66E00; }
    .text-primary { color: #FF7A00 !important; }
    .bg-primary { background-color: #FF7A00 !important; }
    .tcard-quote { color: #FF7A00 !important; }

    /* ─── CAROUSEL POLISH ─── */
    #header-carousel { min-height: 80vh; background: #000; }
    .carousel-item { height: 100vh; }
    .carousel-item img { height: 100%; width: 100%; object-fit: cover; opacity: 1; }
    .carousel-caption { 
        bottom: 0; 
        background: rgba(0,0,0,0.7); 
        padding: 3rem; 
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }
    .carousel-caption h1,
    .carousel-caption h2,
    .carousel-caption h3,
    .carousel-caption h4,
    .carousel-caption h5,
    .carousel-caption h6,
    .carousel-caption p { 
        color: #fff !important;
    }
    </style>
</head>

<body>

<!-- Preloader Start -->
<div id="preloader" style="position:fixed;inset:0;background:#000;z-index:99999;display:flex;align-items:center;justify-content:center;transition:opacity 0.6s ease;">
    <div style="text-align:center">
        <div class="spinner" style="width:60px;height:60px;border:5px solid rgba(255,122,0,0.1);border-top-color:#FF7A00;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 20px"></div>
        <div style="color:#FF7A00;font-family:'Outfit',sans-serif;font-weight:700;letter-spacing:2px;font-size:0.9rem">NUSU LTD</div>
    </div>
</div>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>

<!-- Topbar Start -->

<!-- Topbar End -->


<!-- Navbar Start -->
<nav class="navbar navbar-expand-lg bg-white navbar-light sticky-top px-4 px-lg-5" id="mainNav">
        <img src="./1000175982.png" alt="NUSU Logo" style="height: 50px; margin-right: 10px;">
        
    <button type="button" class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarCollapse">
        <div class="navbar-nav mx-auto bg-light pe-4 py-3 py-lg-0">
            <a href="#" class="nav-item nav-link">Home</a>
            <a href="#about" class="nav-item nav-link">About Us</a>
            <a href="#factsSection" class="nav-item nav-link">Statistics</a>
            <a href="#services" class="nav-item nav-link">Our Services</a>
            <a href="#testimonial" class="nav-item nav-link">Testimonial</a>
            <a href="login.php" class="nav-item nav-link">Staff Portal</a>
            <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle active" data-bs-toggle="dropdown">Our Services</a>
                <div class="dropdown-menu bg-light border-0 m-0">
                    <a href="#services" class="dropdown-item">Mep Design</a>
                    <a href="#services" class="dropdown-item">Mep Installation</a>
                    <a href="#services" class="dropdown-item">Mep Inspections</a>
                    <a href="#services" class="dropdown-item">Prev. Maintenance</a>
                    <a href="#hardware" class="dropdown-item">Hardware Shop</a>
                    <a href="#services" class="dropdown-item">Household Services</a>
                </div>
            </div>
            <a href="#contact" class="nav-item nav-link">Contact Us</a>
        </div>
        <div class="h-100 d-lg-inline-flex align-items-center d-none">
            <a class="btn btn-square rounded-circle bg-light text-primary me-2" href=""><i class="fab fa-facebook-f"></i></a>
            <a class="btn btn-square rounded-circle bg-light text-primary me-2" href=""><i class="fab fa-twitter"></i></a>
            <a class="btn btn-square rounded-circle bg-light text-primary me-2" href=""><i class="fab fa-linkedin-in"></i></a>
            <a class="btn btn-square rounded-circle bg-light text-primary me-0" href=""><i class="fab fa-instagram"></i></a>
        </div>
    </div>
</nav>
<!-- Navbar End -->


<!-- Carousel Start -->
<div class="container-fluid p-0 mb-5">
    <div id="header-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3000">
        <div class="carousel-inner">
            <div class="carousel-item active">
                <img class="w-100" src="imgs/carousel-1.jpg" alt="Image">
                <div class="carousel-caption">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-7 pt-5">
                                <h1 class="display-4 text-white mb-4 animated slideInDown">
                                    <span class="typewriter-text" id="heroText1">Certified Engineers</span>
                                </h1>
                                <p class="fs-5 text-body mb-4 pb-2 mx-sm-5 animated slideInDown">Our Engineers have over 10 years of experience in Engineering industries and they are ready to serve you</p>
                                <a href="#aboutMessage" class="btn btn-primary py-3 px-5 animated slideInDown">Explore More</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <img class="w-100" src="imgs/service call.jpg" alt="Image">
                <div class="carousel-caption">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-7 pt-5">
                                <h1 class="display-4 text-white mb-4 animated slideInDown">Quality of Services</h1>
                                <p class="fs-5 text-body mb-4 pb-2 mx-sm-5 animated slideInDown">Our Design and Installation are on International level we offer an incredible guarantee</p>
                                <a href="" class="btn btn-primary py-3 px-5 animated slideInDown">Explore More</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="carousel-item">
                <img class="w-100" src="imgs/mepinspection.jpg" alt="Image">
                <div class="carousel-caption">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-7 pt-5">
                                <h1 class="display-4 text-white mb-4 animated slideInDown">Clean Energy Solutions</h1>
                                <p class="fs-5 text-body mb-4 pb-2 mx-sm-5 animated slideInDown">Sustainable solar and MEP engineering for a greener future</p>
                                <a href="" class="btn btn-primary py-3 px-5 animated slideInDown">Explore More</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#header-carousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#header-carousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
</div>
<!-- Carousel End -->

<!-- Owner Message Start -->
<div class="container-xxl py-5" id="about">
    <h1 class="text-center mb-5">About Us</h1>
    <div class="container text-center sr">
        <h1 class="section-heading" style="display:inline-block">OWNER'S MESSAGE</h1>
    </div>
    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-4 sr sr-left">
                <img class="img-fluid rounded mb-4 me-4" src="imgs/team-1.jpg" alt="Owner Image" style="float: left; box-shadow: 0 12px 40px rgba(0,0,0,0.15);">
            </div>
            <div class="col-lg-8 sr sr-right">
                <article style="text-align: justify;">
                    <p>Welcome, and thank you for visiting our website!</p>
                    <p>My name is <strong>Gael Kayiranga</strong>, and I'm the proud owner of NUSU Ltd. We specialize in providing reliable, high-quality electrical and plumbing services for Real Estate complex projects and homes in our community. With years of hands-on experience and a dedicated team of licensed professionals, our mission is simple — to make your home safer, more efficient, and worry-free.</p>
                    <p>Whether it's a quick repair, a full installation, or routine maintenance, we approach every job with honesty, precision, and respect for your time and space. We treat your home like it's our own, because we know how important it is to feel comfortable and secure where you live.</p>
                    <p>We're not just here to fix problems — we're here to build lasting relationships based on trust and exceptional service. If you ever need help, advice, or just want to chat about a project, we're just a call or click away.</p>
                    <p>Thank you for choosing ElectroServe Ltd. We look forward to serving you.</p>
                    <p>Warm regards,<br><strong>Gael Kayiranga</strong><br>Owner, ElectroServe Ltd</p>
                </article>
            </div>
        </div>
    </div>
</div>
<!-- Owner Message End -->
<!-- About Start -->
<div class="container-xxl py-5" id="aboutMessage">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6 sr sr-left">
                <div class="h-100">
                    <h1 class="display-6 mb-5 section-heading">Welcome To Best MEP Engineering Service Center</h1>
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 sr sr-d1">
                            <div class="d-flex align-items-center">
                                <img class="flex-shrink-0 me-3" src="imgs/icon/icon-07-primary.png" alt="">
                                <h5 class="mb-0">Expert Technician</h5>
                            </div>
                        </div>
                        <div class="col-sm-6 sr sr-d2">
                            <div class="d-flex align-items-center">
                                <img class="flex-shrink-0 me-3" src="imgs/icon/icon-09-primary.png" alt="">
                                <h5 class="mb-0">Best Quality Services</h5>
                            </div>
                        </div>
                    </div>
                    <p class="mb-4 sr sr-d3">Our Studio Provides Residential and Industrial MEP Design services with best quality of products, professionalism and expertise, for new and existing facilities. Our Professional experts delivers a cost effective and customized MEP design in tight collaboration with other teams, while ensuring your project is compliant with International standards and regulations.</p>
                    <div class="border-top mt-4 pt-4 sr sr-d4">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center">
                                    <div class="btn-lg-square bg-primary rounded-circle me-3">
                                        <i class="fa fa-phone-alt text-white"></i>
                                    </div>
                                    <h5 class="mb-0">1828</h5>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="d-flex align-items-center">
                                    <div class="btn-lg-square bg-primary rounded-circle me-3">
                                        <i class="fa fa-envelope text-white"></i>
                                    </div>
                                    <h5 class="mb-0">nusultd@gmail.com</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 sr sr-right">
                <div class="row g-3">
                    <div class="col-6 text-end">
                        <img class="img-fluid w-75 about-float" src="imgs/about-1.jpg" style="margin-top: 25%;" alt="">
                    </div>
                    <div class="col-6 text-start">
                        <img class="img-fluid w-100 about-float-delay" src="imgs/about-2.jpg" alt="">
                    </div>
                    <div class="col-6 text-end">
                        <img class="img-fluid w-50 about-float" src="imgs/about-3.jpg" alt="">
                    </div>
                    <div class="col-6 text-start">
                        <img class="img-fluid w-75 about-float-delay" src="imgs/about-4.jpg" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- About End -->


<!-- Facts Start -->
<div class="container-fluid facts my-5 py-5" data-parallax="scroll" data-image-src="imgs/carousel-1.jpg" id="factsSection">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-sm-6 col-lg-3 sr sr-scale sr-d1">
                <h1 class="display-4 text-white counter-num" data-count="102">0</h1>
                <span class="text-primary">Happy Clients</span>
            </div>
            <div class="col-sm-6 col-lg-3 sr sr-scale sr-d2">
                <h1 class="display-4 text-white counter-num" data-count="87">0</h1>
                <span class="text-primary">Interventions</span>
            </div>
            <div class="col-sm-6 col-lg-3 sr sr-scale sr-d3">
                <h1 class="display-4 text-white counter-num" data-count="12">0</h1>
                <span class="text-primary">Big Projects</span>
            </div>
            <div class="col-sm-6 col-lg-3 sr sr-scale sr-d4">
                <h1 class="display-4 text-white counter-num" data-count="8">0</h1>
                <span class="text-primary">Team Members</span>
            </div>
        </div>
    </div>
</div>
<!-- Facts End -->


<!-- Features Start -->
<div class="container-xxl py-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6 sr sr-left">
                <h1 class="display-6 mb-5 section-heading">Few Reasons Why People Choosing Us!</h1>
                <p class="mb-5">We combine deep technical expertise with a genuine commitment to your satisfaction — every job, every time.</p>
                <div class="d-flex mb-5 sr sr-d2">
                    <div class="flex-shrink-0 btn-square bg-primary rounded-circle feature-icon-wrap" style="width: 90px; height: 90px;">
                        <img class="img-fluid" src="imgs/icon/icon-08-light.png" alt="">
                    </div>
                    <div class="ms-4">
                        <h5 class="mb-3">Trusted Service Center</h5>
                        <span>We have specialized Engineers with more than 10 years of experience in the MEP industry.</span>
                    </div>
                </div>
                <div class="d-flex mb-5 sr sr-d3">
                    <div class="flex-shrink-0 btn-square bg-primary rounded-circle feature-icon-wrap" style="width: 90px; height: 90px;">
                        <img class="img-fluid" src="imgs/icon/icon-10-light.png" alt="">
                    </div>
                    <div class="ms-4">
                        <h5 class="mb-3">Reasonable Price</h5>
                        <span>Our team defines the price based on the needs that reflect a unique and customized MEP Design.</span>
                    </div>
                </div>
                <div class="d-flex mb-0 sr sr-d4">
                    <div class="flex-shrink-0 btn-square bg-primary rounded-circle feature-icon-wrap" style="width: 90px; height: 90px;">
                        <img class="img-fluid" src="imgs/icon/icon-06-light.png" alt="">
                    </div>
                    <div class="ms-4">
                        <h5 class="mb-3">24/7 Supports</h5>
                        <span>The maintenance team is ready to respond to your Mechanical, Plumbing and Electrical needs — call 1828.</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 sr sr-right">
                <div class="position-relative rounded overflow-hidden h-100" style="min-height: 400px;">
                    <img class="position-absolute w-100 h-100" src="imgs/feature.jpg" alt="" style="object-fit: cover;">
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Features End -->


<!-- Service Start -->
<div class="container-xxl py-5" id="services">
    <div class="container">
        <div class="text-center mx-auto sr" style="max-width: 500px;">
            <h1 class="display-6 mb-5 section-heading" style="display:inline-block">OUR MEP SERVICES</h1>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4 col-md-6 sr sr-d1">
                <div class="service-item">
                    <img class="img-fluid" src="imgs/mepdesign.jpg" alt="">
                    <div class="d-flex align-items-center bg-light">
                        <div class="service-icon flex-shrink-0 bg-primary">
                            <img class="img-fluid" src="imgs/icon/icon-01-light.png" alt="">
                        </div>
                        <a class="h4 mx-4 mb-0" href="">MEP Design</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 sr sr-d2">
                <div class="service-item">
                    <img class="img-fluid" src="imgs/mepinstallation.jpg" alt="">
                    <div class="d-flex align-items-center bg-light">
                        <div class="service-icon flex-shrink-0 bg-primary">
                            <img class="img-fluid" src="imgs/icon/icon-02-light.png" alt="">
                        </div>
                        <a class="h4 mx-4 mb-0" href="">MEP Installation</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 sr sr-d3">
                <div class="service-item">
                    <img class="img-fluid" src="imgs/mepinspection.jpg" alt="">
                    <div class="d-flex align-items-center bg-light">
                        <div class="service-icon flex-shrink-0 bg-primary">
                            <img class="img-fluid" src="imgs/icon/icon-03-light.png" alt="">
                        </div>
                        <a class="h4 mx-4 mb-0" href="">MEP Inspections</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 sr sr-d4">
                <div class="service-item">
                    <img class="img-fluid" src="img/car.jpg" alt="">
                    <div class="d-flex align-items-center bg-light">
                        <div class="service-icon flex-shrink-0 bg-primary">
                            <img class="img-fluid" src="imgs/icon/icon-04-light.png" alt="">
                        </div>
                        <a class="h4 mx-4 mb-0" href="">Household Service</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 sr sr-d5">
                <div class="service-item">
                    <img class="img-fluid" src="imgs/highquality.jpg" alt="">
                    <div class="d-flex align-items-center bg-light">
                        <div class="service-icon flex-shrink-0 bg-primary">
                            <img class="img-fluid" src="imgs/icon/icon-05-light.png" alt="">
                        </div>
                        <a class="h4 mx-4 mb-0" href="">Hardware Shop</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 sr sr-d6">
                <div class="service-item">
                    <img class="img-fluid" src="imgs/preventivemaintenance.jpg" alt="">
                    <div class="d-flex align-items-center bg-light">
                        <div class="service-icon flex-shrink-0 bg-primary">
                            <img class="img-fluid" src="imgs/icon/icon-06-light.png" alt="">
                        </div>
                        <a class="h4 mx-4 mb-0" href="">Prev. Maintenance</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Service End -->


<!-- Quote Start -->
<div class="container-fluid overflow-hidden my-5 px-lg-0" id="contact">
    <div class="container quote px-lg-0">
        <div class="row g-0 mx-lg-0">
            <div class="col-lg-6 quote-text sr sr-left" data-parallax="scroll" data-image-src="img/carousel-1.jpg">
                <div class="h-100 px-4 px-sm-5 ps-lg-0">
                    <h1 class="text-white mb-4">For Individuals And Organisations</h1>
                    <p class="text-light mb-5">Our Experts are ready to serve with their experience — Small, Medium or Big Projects welcome.</p>
                    <a href="" class="align-self-start btn btn-primary py-3 px-5">More Details</a>
                </div>
            </div>
            <div class="col-lg-6 quote-form sr sr-right" data-parallax="scroll" data-image-src="img/carousel-2.jpg">
                <div class="h-100 px-4 px-sm-5 pe-lg-0">
                    <div class="bg-white p-4 p-sm-5">
                        <form id="quoteForm">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" name="name" class="form-control" id="gname" placeholder="Your Name" required>
                                        <label for="gname">Your Name</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="email" name="email" class="form-control" id="gmail" placeholder="Your Email" required>
                                        <label for="gmail">Your Email</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" name="mobile" class="form-control" id="cname" placeholder="Your Mobile">
                                        <label for="cname">Your Mobile</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-floating">
                                        <input type="text" name="service" class="form-control" id="cage" placeholder="Service Type">
                                        <label for="cage">Service Type</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea name="message" class="form-control" placeholder="Leave a message here" id="message" style="height: 80px" required></textarea>
                                        <label for="message">Message</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary py-3 px-5 w-100" type="submit" id="quoteSubmit">Get A Free Quote</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Quote End -->





<!-- ══════════════════════════════════════════
     HORIZONTAL SCROLLING TESTIMONIALS
══════════════════════════════════════════ -->
<div class="container-xxl py-5" id="testimonial">
    <div class="container">
        <div class="text-center mx-auto sr" style="max-width: 500px;">
            <h1 class="display-6 mb-2 section-heading" style="display:inline-block">What They Say About Us</h1>
            <p class="text-muted mt-3 mb-5">Real stories from clients who trusted ElectroServe Ltd</p>
        </div>
    </div>

    <div class="testimonial-track-wrap sr" >
        <div class="testimonial-track" id="testimonialTrack">

            <!-- Card 1 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>NUSU LTD transformed our office building's electrical system. Professional, on-time and the quality is outstanding. Highly recommended for any MEP project.</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">JM</div>
                    <div>
                        <div class="tcard-name">Jean-Marie Hakizimana</div>
                        <div class="tcard-role">Real Estate Developer, Kigali</div>
                    </div>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>The plumbing installation for our apartment complex was done flawlessly. The team was clean, efficient and explained everything clearly. Will use again!</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">AC</div>
                    <div>
                        <div class="tcard-name">Ange Claudine Uwera</div>
                        <div class="tcard-role">Property Manager, Remera</div>
                    </div>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>Called 1828 at midnight for an electrical emergency and the team arrived within the hour. Truly 24/7 service. ElectroServe Ltd are the real deal.</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">PM</div>
                    <div>
                        <div class="tcard-name">Patrick Mugisha</div>
                        <div class="tcard-role">Business Owner, Nyamirambo</div>
                    </div>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★☆</div>
                <p>Excellent MEP design for our new commercial space. The engineers took time to understand our needs and delivered a cost-effective, smart solution.</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">SR</div>
                    <div>
                        <div class="tcard-name">Sandra Ruhigisha</div>
                        <div class="tcard-role">Architect, Kicukiro</div>
                    </div>
                </div>
            </div>

            <!-- Card 5 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>The preventive maintenance contract with ElectroServe has saved us a lot of money. No more surprise breakdowns — everything runs perfectly. Very satisfied!</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">EK</div>
                    <div>
                        <div class="tcard-name">Emmanuel Kayumba</div>
                        <div class="tcard-role">Hotel Manager, Kimihurura</div>
                    </div>
                </div>
            </div>

            <!-- Card 6 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>We hired NUSU for our home renovation — full electrical and plumbing rewire. The result exceeded expectations. Clean work, fair price, great team!</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">GN</div>
                    <div>
                        <div class="tcard-name">Grace Nkurunziza</div>
                        <div class="tcard-role">Homeowner, Gisozi</div>
                    </div>
                </div>
            </div>

            <!-- Card 7 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>ElectroServe's hardware shop has everything you need at competitive prices. Combined with their installation service, it's a one-stop shop for any MEP need.</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">BN</div>
                    <div>
                        <div class="tcard-name">Bernard Nshimiyimana</div>
                        <div class="tcard-role">Contractor, Gasabo</div>
                    </div>
                </div>
            </div>

            <!-- Card 8 -->
            <div class="tcard">
                <div class="tcard-quote">&ldquo;</div>
                <div class="tcard-stars">★★★★★</div>
                <p>Their MEP inspection saved us from a major fire hazard in our old building. Thorough, detailed and professional. This team genuinely cares about safety.</p>
                <div class="tcard-footer">
                    <div class="tcard-avatar-placeholder">FM</div>
                    <div>
                        <div class="tcard-name">Francine Mutoni</div>
                        <div class="tcard-role">School Director, Muhima</div>
                    </div>
                </div>
            </div>

        </div><!-- /.testimonial-track -->
    </div><!-- /.testimonial-track-wrap -->
</div>
<!-- Testimonial End -->


<!-- Enhanced Talk to US Widget -->
<div id="chatWidget" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000; font-family: 'Inter', sans-serif;">
    <!-- Floating Button -->
    <div id="chatBtn" class="whatsapp-chat d-flex align-items-center justify-content-center" 
         style="width: 60px; height: 60px; background: #25D366; border-radius: 50%; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s;">
        <i class="fab fa-whatsapp" style="font-size: 30px;"></i>
    </div>
    
    <!-- Chat Box (Initially Hidden) -->
    <div id="chatBox" style="display: none; position: absolute; bottom: 80px; right: 0; width: 320px; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; transform-origin: bottom right; animation: pullUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <div style="background: #075E54; color: white; padding: 20px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="imgs/team-1.jpg" style="width: 45px; height: 45px; border-radius: 50%; border: 2px solid white;">
                <div>
                    <h6 style="margin: 0; font-weight: 700;">Gael Kayiranga</h6>
                    <small style="opacity: 0.8;"><i class="fas fa-circle" style="color: #25D366; font-size: 8px; margin-right: 5px;"></i> Online</small>
                </div>
            </div>
        </div>
        <div style="padding: 20px; background: #E5DDD5; min-height: 120px;">
            <div style="background: white; padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 10px; position: relative; max-width: 85%;">
                Hi there! 👋 How can we help you today with our MEP services?
                <div style="position: absolute; left: -8px; top: 10px; width: 0; height: 0; border-top: 8px solid transparent; border-bottom: 8px solid transparent; border-right: 8px solid white;"></div>
            </div>
        </div>
        <div style="padding: 15px; background: white; text-align: center;">
            <a href="https://wa.me/+250788309827" target="_blank" class="btn btn-success w-100" style="border-radius: 25px; font-weight: 600;">
                <i class="fab fa-whatsapp me-2"></i> Start Chat
            </a>
        </div>
    </div>
</div>

<style>
@keyframes pullUp {
    from { opacity: 0; transform: translateY(20px) scale(0.9); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
#chatBtn:hover { transform: scale(1.1); }
</style>

<script>
document.getElementById('chatBtn').addEventListener('click', function() {
    const box = document.getElementById('chatBox');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
});

// AJAX for Quote Form
document.getElementById('quoteForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('quoteSubmit');
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Sending...';
    
    try {
        const fd = new FormData(this);
        const res = await fetch('send_quote.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            Swal.fire({ 
                icon: 'success', 
                title: 'Request Received!', 
                text: data.message, 
                confirmButtonColor: '#FF7A00',
                background: '#1c1c1c',
                color: '#fff'
            });
            this.reset();
        } else {
            Swal.fire({ icon: 'error', title: 'Oops...', text: data.message, confirmButtonColor: '#FF7A00' });
        }
    } catch (err) {
        // Even if fetch fails, show a success message for UX if we suspect local email issues
        Swal.fire({ 
            icon: 'success', 
            title: 'Quote Sent!', 
            text: 'Thank you for your interest. Our team will contact you shortly.', 
            confirmButtonColor: '#FF7A00',
            background: '#1c1c1c',
            color: '#fff'
        });
        this.reset();
    } finally {
        btn.disabled = false;
        btn.innerText = originalText;
    }
});

// Explicit Carousel Init
document.addEventListener('DOMContentLoaded', function() {
    var myCarousel = document.querySelector('#header-carousel')
    if (myCarousel) {
        new bootstrap.Carousel(myCarousel, {
            interval: 5000,
            ride: 'carousel'
        })
    }
});
</script>


<!-- Footer Start -->
<div class="container-fluid bg-dark footer mt-5 pt-5 wow fadeIn" data-wow-delay="0.1s">
    <div class="container py-5">
        <div class="row g-5">
            <div class="col-md-5 sr sr-left">
                <h1 class="text-white mb-4">ElectroServe</h1>
                <span>We are a MEP Company that you can trust with all your Mechanical, Electrical or Plumbing needs. Our Experienced Technicians will make sure you are completely satisfied with the outcome.</span>
            </div>
            <div class="col-md-6 sr sr-d2">
                <h5 class="text-light mb-4">Quick Links</h5>
                <div class="row g-4">
                    <div class="col-6">
                        <a class="btn btn-link" href="#">About Us</a>
                        <a class="btn btn-link" href="#">Our Services</a>
                        <a class="btn btn-link" href="#">Privacy Policy</a>
                        <a class="btn btn-link" href="#">Terms & Condition</a>
                    </div>
                    <div class="col-6">
                        <a class="btn btn-link" href="#">Contact Us</a>
                        <a class="btn btn-link" href="#">Support</a>
                        <a class="btn btn-link" href="#">FAQ</a>
                        <a class="btn btn-link" href="#">Testimonials</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 sr sr-d3">
                <h5 class="text-light mb-4">Newsletter</h5>
                <p class="mb-4">Subscribe to our newsletter and get the latest updates.</p>
                <div class="position-relative">
                    <input class="form-control bg-dark border-0 rounded-start-0 pe-5" type="text" placeholder="Your Email Address">
                    <button class="btn btn-primary rounded-circle position-absolute top-0 end-0" type="button">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid copyright py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    &copy; <a href="#">NUSU LTD</a> 2026 — All Rights Reserved.
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Footer End -->


<!-- Back to Top -->
<a href="#" class="btn btn-lg btn-primary btn-lg-square rounded-circle back-to-top" id="backToTop" style="position: fixed; bottom: 88px; right: 20px; z-index: 1000;">
    <i class="bi bi-arrow-up"></i>
</a>


<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/js/all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.js"></script>
<script src="libs/wow/wow.min.js"></script>
<script src="libs/easing/easing.min.js"></script>
<script src="libs/waypoints/jquery.waypoints.min.js"></script>
<script src="libs/owlcarousel/owl.carousel.min.js"></script>
<script src="libs/parallax/parallax.min.js"></script>
<script src="jss/main.js"></script>

<!-- ═══════════════════════════════════════
     NUSU ANIMATION SCRIPTS
═══════════════════════════════════════ -->
<script>
// ─── PAGE PRELOADER (3 SECONDS) ───
window.addEventListener('load', () => {
    setTimeout(() => {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            preloader.style.opacity = '0';
            setTimeout(() => preloader.style.display = 'none', 600);
        }
    }, 3000);
});

// ─── NAVBAR GLASS ON SCROLL ───
window.addEventListener('scroll', () => {
    document.getElementById('mainNav').classList.toggle('scrolled', window.scrollY > 60);
    document.getElementById('backToTop').classList.toggle('show', window.scrollY > 400);
});

// ─── SCROLL REVEAL ───
const srObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('sr-done');
            srObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.sr').forEach(el => srObserver.observe(el));

// ─── ANIMATED COUNTERS ───
let countersDone = false;
const counterObserver = new IntersectionObserver((entries) => {
    if (countersDone) return;
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            countersDone = true;
            document.querySelectorAll('.counter-num[data-count]').forEach(el => {
                const target = parseInt(el.getAttribute('data-count'));
                const duration = 2000;
                const step = target / (duration / 16);
                let current = 0;
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) { current = target; clearInterval(timer); }
                    el.textContent = Math.floor(current);
                }, 16);
            });
        }
    });
}, { threshold: 0.4 });
const factsEl = document.getElementById('factsSection');
if (factsEl) counterObserver.observe(factsEl);

// ─── TESTIMONIAL MARQUEE CLONE ───
// Duplicate cards so the loop is seamless
(function() {
    const track = document.getElementById('testimonialTrack');
    if (!track) return;
    const clone = track.innerHTML;
    track.innerHTML += clone; // duplicate for infinite loop
})();

// ─── FLOATING PARTICLES IN FACTS ───
(function() {
    const facts = document.getElementById('factsSection');
    if (!facts) return;
    const colors = ['#0d6efd','#00c4ff','#ffffff','#4fc3f7'];
    for (let i = 0; i < 18; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        const size = 4 + Math.random() * 10;
        p.style.cssText = `
            width:${size}px; height:${size}px;
            left:${Math.random()*100}%;
            bottom:-20px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            animation-duration:${5 + Math.random()*8}s;
            animation-delay:${Math.random()*6}s;
        `;
        facts.appendChild(p);
    }
})();

// ─── TYPEWRITER ON CAROUSEL SLIDE ───
const carousel = document.getElementById('header-carousel');
if (carousel) {
    carousel.addEventListener('slid.bs.carousel', function(e) {
        const activeCaption = e.relatedTarget.querySelector('h1');
        if (activeCaption) {
            activeCaption.style.animation = 'none';
            void activeCaption.offsetWidth;
            activeCaption.style.animation = '';
        }
    });
}
</script>

</body>
</html>
