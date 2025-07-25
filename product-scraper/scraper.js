const puppeteer = require('puppeteer');
const axios = require('axios');
const pool = require('./db');
require('dotenv').config();

async function getProducts() {
  const [rows] = await pool.query(`
    SELECT u.ean, u.shop_id, u.url
    FROM urls u
    JOIN products p ON p.ean = u.ean
  `);
  return rows;
}

async function scrapeAndUpload(product) {
  console.log(`Launching browser for ${product.ean}`);
  const browser = await puppeteer.launch({ headless: 'false' });
  const page = await browser.newPage();
  console.log(`Navigating to ${product.url}`);
  await page.goto(product.url, { waitUntil: 'networkidle2', timeout: 5000 }); // Increased timeout for better reliability
  console.log(`Page loaded for ${product.ean}`);

  let isAvailable = false;
  
  if (product.shop_id === 1) {
    isAvailable = await scarpeRossmann(page, product);
  } else if (product.shop_id === 2) {
    // Implement scraping logic for other shops here
    console.log(`Scraping logic for shop_id ${product.shop_id} not implemented yet.`);
  }

  console.log('Avaiable: ' + isAvailable);

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

  console.log(products);

  for (const product of products) {
    try {
      await scrapeAndUpload(product);
    } catch (err) {
      console.error(`⚠️ Error scraping ${product.url}: ${err.message}`);
    }
  }

  console.log('✅ All products processed.');
  process.exit(0);
})();






//Rossmann scraping function
async function scarpeRossmann(page, product) {
  console.log(`Scraping Rossmann for ${product.ean}`);
  console.log(`Checking availability for ${product.ean}`);
  const availableElements = await page.$$('.ozund6-0.cTGPjz');
  console.log(`Found ${availableElements.length} availability elements for ${product.ean}`);

  if (availableElements.length === 0) {
    console.warn(`⚠️ No availability elements found for ${product.ean}`);
    await browser.close();
    return;
  }
  for (const element of availableElements) {
    const text = await page.evaluate(el => el.innerText, element);
    if (text.includes('Online készleten')) {
      return true;
    } else if (text.includes('Nincs készleten')) {
      return false;
    }
  }
}