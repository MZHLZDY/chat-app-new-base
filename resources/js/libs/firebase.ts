// Import the functions you need from the SDKs you need
import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getDatabase } from "firebase/database";
import { getStorage } from "firebase/storage";
import { getAuth, signInAnonymously } from "firebase/auth";
// TODO: Add SDKs for Firebase products that you want to use
// https://firebase.google.com/docs/web/setup#available-libraries

// Your web app's Firebase configuration
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyDVb-Jab7CWhzKCD6Zcr_OyGTs7d2qC-ik",
  authDomain: "chat-app-new-base.firebaseapp.com",
  databaseURL: "https://chat-app-new-base-default-rtdb.asia-southeast1.firebasedatabase.app",
  projectId: "chat-app-new-base",
  storageBucket: "chat-app-new-base.firebasestorage.app",
  messagingSenderId: "641895816036",
  appId: "1:641895816036:web:3489f4f41da68b5b70b871",
  measurementId: "G-HTY2G8MWX7"
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
const storage = getStorage(app);
export { db, auth, storage };
export const database = getDatabase(app);
