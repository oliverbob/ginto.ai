<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <!-- Required Open Graph meta tags -->
    <meta property="og:title" content="SmartFi Presentation />
    <meta property="og:description" content="SmartFi CH360" />
    <!-- <meta property="og:url" content="https://example.com/page" /> -->
    <meta property="og:type" content="website" />
    <!-- <meta property="og:image" content="https://example.com/thumbnail.jpg" /> -->
    <title>SmartFi Vendo WiFi Coverage Map with Households</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            max-width: 1000px;
            width: 100%;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 2.5em;
            background: linear-gradient(45deg, #fff, #f0f8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            text-align: center;
            margin-bottom: 30px;
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .map-container {
            position: relative;
            width: 700px;
            height: 700px;
            margin: 0 auto;
            background: radial-gradient(circle, #1a1a2e 0%, #16213e 100%);
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 50px rgba(102, 126, 234, 0.5);
            overflow: hidden;
        }
        
        .coverage-ring {
            position: absolute;
            border-radius: 50%;
            border: 2px solid;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.4;
        }
        
        .ring-10km {
            width: 200px;
            height: 200px;
            border-color: #ff6b6b;
            background: radial-gradient(circle, transparent 0%, rgba(255, 107, 107, 0.1) 100%);
        }
        
        .ring-20km {
            width: 350px;
            height: 350px;
            border-color: #4ecdc4;
            background: radial-gradient(circle, transparent 60%, rgba(78, 205, 196, 0.1) 100%);
        }
        
        .ring-30km {
            width: 500px;
            height: 500px;
            border-color: #45b7d1;
            background: radial-gradient(circle, transparent 70%, rgba(69, 183, 209, 0.1) 100%);
        }
        
        .ring-35km {
            width: 650px;
            height: 650px;
            border-color: #96ceb4;
            background: radial-gradient(circle, transparent 85%, rgba(150, 206, 180, 0.1) 100%);
        }
        
        .center-point {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 25px;
            height: 25px;
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            border-radius: 50%;
            box-shadow: 0 0 25px #ffd700;
            z-index: 100;
        }
        
        .center-point::after {
            content: '';
            position: absolute;
            top: -15px;
            left: -15px;
            width: 55px;
            height: 55px;
            border: 2px solid #ffd700;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(2);
                opacity: 0;
            }
        }
        
        .tower-label {
            position: absolute;
            top: 45%;
            left: 55%;
            background: rgba(255, 215, 0, 0.9);
            color: #000;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 101;
        }
        
        /* Household clusters */
        .household-cluster {
            position: absolute;
            z-index: 50;
        }
        
        .house {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .house::before {
            content: '';
            position: absolute;
            top: -6px;
            left: 2px;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 6px solid #388E3C;
        }
        
        .house.connected {
            background: #81C784;
            box-shadow: 0 0 8px rgba(129, 199, 132, 0.8);
            animation: connected-pulse 3s infinite;
        }
        
        @keyframes connected-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        .house:hover {
            transform: scale(1.5);
            background: #FFC107;
        }
        
        /* Community areas */
        .community {
            position: absolute;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }
        
        .building {
            width: 8px;
            height: 15px;
            background: #607D8B;
            margin: 2px;
        }
        
        .building.apartment {
            height: 20px;
            background: #455A64;
        }
        
        /* Additional signal rays extending to edges */
        .signal-ray {
            position: absolute;
            height: 2px;
            background: linear-gradient(90deg, rgba(255, 215, 0, 0.8) 0%, rgba(255, 215, 0, 0.3) 50%, rgba(255, 215, 0, 0) 100%);
            transform-origin: left center;
            animation: signal-pulse 3s infinite;
            z-index: 15;
        }
        
        @keyframes signal-pulse {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }
        
        .directional-beam {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 325px;
            height: 1px;
            background: linear-gradient(90deg, rgba(255, 215, 0, 0.9) 0%, rgba(255, 215, 0, 0.1) 100%);
            transform-origin: left center;
            animation: beam-sweep 6s infinite;
            z-index: 10;
        }
        
        @keyframes beam-sweep {
            0% { transform: translate(0, -50%) rotate(0deg); opacity: 0.8; }
            25% { transform: translate(0, -50%) rotate(90deg); opacity: 0.8; }
            50% { transform: translate(0, -50%) rotate(180deg); opacity: 0.8; }
            75% { transform: translate(0, -50%) rotate(270deg); opacity: 0.8; }
            100% { transform: translate(0, -50%) rotate(360deg); opacity: 0.8; }
        }
        
        .distance-labels {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .label {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            transform: translate(-50%, -50%);
        }
        
        .label-10km {
            top: 22%;
            left: 50%;
            background: rgba(255, 107, 107, 0.9);
        }
        
        .label-20km {
            top: 14%;
            left: 50%;
            background: rgba(78, 205, 196, 0.9);
        }
        
        .label-30km {
            top: 8%;
            left: 50%;
            background: rgba(69, 183, 209, 0.9);
        }
        
        .label-35km {
            top: 4%;
            left: 50%;
            background: rgba(150, 206, 180, 0.9);
        }
        
        .info-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 18px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .info-card h3 {
            margin: 0 0 8px 0;
            color: #ffd700;
            font-size: 1.1em;
        }
        
        .info-card p {
            margin: 0;
            font-size: 0.9em;
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }
        
        .legend-icon {
            width: 16px;
            height: 16px;
            border-radius: 2px;
        }
        
        .tower-icon {
            width: 16px;
            height: 16px;
            background: #ffd700;
            border-radius: 50%;
        }
        
        .house-icon {
            background: #4CAF50;
        }
        
        .connected-icon {
            background: #81C784;
            box-shadow: 0 0 4px rgba(129, 199, 132, 0.8);
        }
        
        .compass {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 10px;
        }
        
        .compass::before {
            content: 'N';
            position: absolute;
            top: 3px;
            color: #ff6b6b;
            font-size: 12px;
        }
        
        .stats-summary {
            text-align: center;
            margin-top: 20px;
            font-size: 1em;
            opacity: 0.9;
        }
        
        .coverage-indicator {
            position: absolute;
            font-size: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏠 SmartFi CH360° Vendo WiFi Household Coverage</h1>
        <p class="subtitle"><a href="https://prezi.com/view/nvtfiAzBmklsYs7ZEQoN/">Click here to see Business Process Presentation on Prexi.</a></p>
        <p class="subtitle">Serving Communities • 360° Distribution • 10-35km Network Reach</p>
        
        <div class="map-container">
            <div class="compass"></div>
            
            <!-- Coverage rings -->
            <div class="coverage-ring ring-35km"></div>
            <div class="coverage-ring ring-30km"></div>
            <div class="coverage-ring ring-20km"></div>
            <div class="coverage-ring ring-10km"></div>
            
            <!-- WiFi Tower -->
            <div class="center-point"></div>
            <div class="tower-label">WiFi Tower</div>
            
            <!-- Household clusters in different zones -->
            
            <!-- 10km zone - High density residential -->
            <div class="household-cluster" style="top: 35%; left: 60%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: 5px; left: 15px;"></div>
                <div class="house connected" style="top: -8px; left: 30px;"></div>
                <div class="house connected" style="top: 12px; left: 45px;"></div>
                <div class="coverage-indicator" style="top: -25px; left: 20px;">Village Center</div>
            </div>
            
            <div class="household-cluster" style="top: 55%; left: 40%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: -10px; left: 20px;"></div>
                <div class="house connected" style="top: 8px; left: 40px;"></div>
                <div class="coverage-indicator" style="top: -25px; left: 15px;">Residential Area</div>
            </div>
            
            <!-- 15km zone -->
            <div class="household-cluster" style="top: 25%; left: 35%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: 15px; left: 20px;"></div>
                <div class="house connected" style="top: -5px; left: 40px;"></div>
                <div class="house connected" style="top: 20px; left: 60px;"></div>
                <div class="house connected" style="top: 5px; left: 80px;"></div>
            </div>
            
            <div class="household-cluster" style="top: 65%; left: 65%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: -12px; left: 25px;"></div>
                <div class="house connected" style="top: 10px; left: 50px;"></div>
                <div class="coverage-indicator" style="top: -30px; left: 20px;">Suburb District</div>
            </div>
            
            <!-- 20km zone -->
            <div class="household-cluster" style="top: 20%; left: 70%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: 18px; left: 25px;"></div>
                <div class="house connected" style="top: -8px; left: 50px;"></div>
            </div>
            
            <div class="household-cluster" style="top: 75%; left: 30%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: 15px; left: 30px;"></div>
                <div class="house connected" style="top: -10px; left: 60px;"></div>
                <div class="coverage-indicator" style="top: -25px; left: 25px;">Rural Community</div>
            </div>
            
            <!-- 25-30km zone -->
            <div class="household-cluster" style="top: 15%; left: 50%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: 20px; left: 35px;"></div>
                <div class="coverage-indicator" style="top: -20px; left: 15px;">Remote Area</div>
            </div>
            
            <div class="household-cluster" style="top: 80%; left: 55%;">
                <div class="house connected" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: -15px; left: 40px;"></div>
                <div class="house connected" style="top: 25px; left: 80px;"></div>
            </div>
            
            <!-- 30-35km zone - Sparse coverage -->
            <div class="household-cluster" style="top: 10%; left: 85%;">
                <div class="house" style="top: 0px; left: 0px;"></div>
                <div class="house" style="top: 30px; left: 50px;"></div>
                <div class="coverage-indicator" style="top: -20px; left: 20px;">Edge Coverage</div>
            </div>
            
            <div class="household-cluster" style="top: 85%; left: 15%;">
                <div class="house" style="top: 0px; left: 0px;"></div>
                <div class="house connected" style="top: -20px; left: 60px;"></div>
            </div>
            
            <!-- Community buildings -->
            <div class="community" style="top: 42%; left: 52%;">
                <div class="building apartment"></div>
                <div class="building apartment"></div>
                <div class="building"></div>
                <div class="coverage-indicator" style="top: -15px; left: 0px;">Town Center</div>
            </div>
            
            <!-- Signal waves reaching to 35km -->
            <div class="signal-waves">
                <div class="wave"></div>
                <div class="wave"></div>
                <div class="wave"></div>
                <div class="wave"></div>
                <div class="wave"></div>
            </div>
            
            <!-- Directional beam sweeping to 35km -->
            <div class="directional-beam"></div>
            
            <!-- Signal rays extending to various zones -->
            <div class="signal-ray" style="top: 50%; left: 50%; width: 150px; transform: rotate(45deg); animation-delay: 0s;"></div>
            <div class="signal-ray" style="top: 50%; left: 50%; width: 200px; transform: rotate(-30deg); animation-delay: 0.5s;"></div>
            <div class="signal-ray" style="top: 50%; left: 50%; width: 250px; transform: rotate(120deg); animation-delay: 1s;"></div>
            <div class="signal-ray" style="top: 50%; left: 50%; width: 300px; transform: rotate(-120deg); animation-delay: 1.5s;"></div>
            <div class="signal-ray" style="top: 50%; left: 50%; width: 180px; transform: rotate(15deg); animation-delay: 2s;"></div>
            <div class="signal-ray" style="top: 50%; left: 50%; width: 320px; transform: rotate(-75deg); animation-delay: 2.5s;"></div>
            
            <!-- Distance labels -->
            <div class="distance-labels">
                <div class="label label-10km">10km - Dense Coverage</div>
                <div class="label label-20km">20km - Suburban</div>
                <div class="label label-30km">30km - Rural</div>
                <div class="label label-35km">35km - Edge Coverage</div>
            </div>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-icon tower-icon"></div>
                <span>WiFi Tower</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon house-icon"></div>
                <span>Households</span>
            </div>
            <div class="legend-item">
                <div class="legend-icon connected-icon"></div>
                <span>Connected Homes</span>
            </div>
        </div>
        
        <div class="info-panel">
            <div class="info-card">
                <h3>🏘️ Households Served</h3>
                <p>~3,600 Active<br>~7200 Potential</p>
            </div>
            <div class="info-card">
                <h3>📍 Coverage Zones</h3>
                <p>Urban • Suburban<br>Rural • Remote</p>
            </div>
            <div class="info-card">
                <h3>🌐 Service Area</h3>
                <p>3,847 km²<br>Multi-Community</p>
            </div>
            <div class="info-card">
                <h3>📶 Connection Rate</h3>
                <p>95% (0-20km)<br>75% (20-35km)</p>
            </div>
            <div class="info-card">
                <h3>🏠 Coverage Density</h3>
                <p>High: 10-15km<br>Medium: 15-25km</p>
            </div>
            <div class="info-card">
                <h3>💰 Revenue Zones</h3>
                <p>Premium: Urban<br>Standard: Rural</p>
            </div>
            <div class="info-card">
                <h3>💰 SmartFi System</h3>
                <p>Urban: CH360<br>Rural: CH45</p>
            </div>
            <div class="info-card">
                <h3>💰 SmartFi 45°</h3>
                <p>SmartFi CH45<br>P3,600,000 x 20% = P720,000/m</p>
            </div>
            <div class="info-card">
                <h3>💰 SmartFi CH360° Formation</h3>
                <p>SmartFi 360°<br>P3,600,000 x 20% = P720,000 x 6 = P4.32M/m</p>
            </div>
            <div class="info-card">
                <h3>💰 Packaged Intelligence</h3>
                <p>Intelligence of Things (iOT)<br>Surveillance System on higher plans</p>
            </div>
        </div>
        
        <div class="stats-summary">
            <p><strong>Network Impact:</strong> Serving 15+ Communities • 3,600+ Connected Households • 360° Coverage Distribution</p>
            <p><strong>Service Model:</strong> Vendo WiFi Ecommerce • Tiered Coverage • Community-Based Access</p>
        </div>
    </div>

    <script>
        // Add interactivity to houses
        document.querySelectorAll('.house').forEach(house => {
            house.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.5)';
                this.style.background = '#FFC107';
            });
            
            house.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                if (this.classList.contains('connected')) {
                    this.style.background = '#81C784';
                } else {
                    this.style.background = '#4CAF50';
                }
            });
        });
    </script>
</body>
</html>