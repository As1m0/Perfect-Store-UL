const puppeteer = require('puppeteer');
const axios = require('axios');
const pool = require('./db');
require('dotenv').config();

async function getProducts() {
  const [rows] = await pool.query(`
    SELECT u.ean, u.shop_id, u.url
    FROM urls u
    JOIN products p ON p.ean = u.ean
    WHERE u.shop_id = 3
  `);
  return rows;
}

async function scrapeAndUpload(product) {
  console.log(`Launching browser for ${product.ean}`);
  const browser = await puppeteer.launch({ headless: 'true' });
  const page = await browser.newPage();

  // Enhanced anti-detection measures
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, 'webdriver', {
      get: () => undefined,
    });
  });

  // Set viewport and additional headers to mimic real browser
  await page.setViewport({ width: 1366, height: 768 });
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

  // Set extra HTTP headers
  await page.setExtraHTTPHeaders({
    'Accept-Language': 'en-US,en;q=0.9,hu;q=0.8',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Encoding': 'gzip, deflate, br',
    'DNT': '1',
    'Connection': 'keep-alive',
    'Upgrade-Insecure-Requests': '1'
  });

  console.log(`Navigating to ${product.url}`);
  await page.goto(
    product.url,
    {
      waitUntil: 'networkidle2',
      timeout: 15000  // Increased timeout for better reliability
    });
  console.log(`Page loaded for ${product.ean}`);


  //Check availability
  let isAvailable = false;
  isAvailable = await scarpeKifli(page, product);
  console.log('Avaiable: ' + isAvailable);

  //Upload result
  console.log(`Uploading result for ${product.ean}`);
  try {
    await axios.post(process.env.API_URL, {
      token: process.env.API_TOKEN,
      ean: product.ean,
      shop_id: product.shop_id,
      is_available: isAvailable ? 1 : 0
    });
    console.log(`✅ Uploaded: ${product.ean} | Shop ${product.shop_id}`);
  } catch (error) {
    if (error.response && error.response.data) {
      console.error(`❌ Upload failed: ${product.ean} | ${error.response.data.error || error.message}`);
    } else {
      console.error(`❌ Upload failed: ${product.ean} | ${error.message}`);
    }
  }

  await browser.close();
  console.log(`Browser closed for ${product.ean}`);
}

(async () => {
  const products = await getProducts();
  const totalProducts = products.length;
  console.log(`Found ${totalProducts} products to scrape.`);
  //console.log(products);

  for (let i = 0; i < products.length; i++) {
    try {
      if (i > 0) {
        const waitTime = getRandomWaitTime();
        console.log(`Waiting ${waitTime}ms before next product...`);
        await new Promise(resolve => setTimeout(resolve, waitTime));
      }
      console.log(`Scraping product ${i + 1} of ${totalProducts}`);
      await scrapeAndUpload(products[i]);
    } catch (err) {
      console.error(`⚠️ Error scraping ${products[i].url}: ${err.message}`);
    }
  }
  console.log('✅ All products processed.');
  process.exit(0);
})();


// Function to generate random wait time between requests
function getRandomWaitTime(min = 3000, max = 8000) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

//Kifli scraping function
async function scarpeKifli(page, product) {
  console.log(`Scraping Rossmann for ${product.ean}`);
  console.log(`Checking availability for ${product.ean}`);
  const productDetail = await page.$('.productDetail__soldOut');
  if(productDetail) {
    console.log(`Product ${product.ean} is sold out.`);
    return false;
  } else {
    console.log(`Product ${product.ean} is avaiable.`);
    return true;
  }
}