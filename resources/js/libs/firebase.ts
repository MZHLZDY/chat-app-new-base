// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getDatabase } from "firebase/database";
import { getAuth, signInAnonymously } from "firebase/auth";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyDhLDKFcdYIweRa1CYxa0ZVVXiNUesBA4c",
  authDomain: "chat-app-6b2d7.firebaseapp.com",
  databaseURL: "https://chat-app-6b2d7-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "chat-app-6b2d7",
  storageBucket: "chat-app-6b2d7.firebasestorage.app",
  messagingSenderId: "556369874866",
  appId: "1:556369874866:web:d16dca86503ff4c5859eab",
  measurementId: "G-LWSJR9QY7Y"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);
const db = getDatabase(app);
const auth = getAuth(app);
signInAnonymously(auth)
  .then(() => {
    console.log('Firebase Auth: Signed in anonymously');
  })
  .catch((error) => {
    console.error('Firebase Auth Error:', error);
  });

export { db, auth };