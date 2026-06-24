import { defineStore } from "pinia";

// Mirrors the SOCIETY entity: id, name, faculty, advisor_id.
// Members/founded/coverUrl/logoColor are presentational extras carried
// over from the Figma prototype (not part of the core ER schema) — keep
// them here for now since the UI depends on them; drop or move them to a
// "society profile" concept later if the backend schema diverges.
const SOCIETIES = {
  "IEEE UTM Student Branch": {
    desc: "Advancing technology for humanity. We run technical workshops, competitions, and industry visits for engineering and computing students at UTM. As part of the global IEEE network, our members gain access to world-class resources, publications, and professional development opportunities.",
    faculty: "Faculty of Electrical Engineering",
    members: 240,
    founded: "2004",
    coverUrl: "https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#1A3A6B",
  },
  "UTM Sports Club": {
    desc: "Promoting health, teamwork, and competitive spirit across all UTM faculties through regular inter-faculty tournaments, recreational leagues, and fitness programmes. We welcome athletes and beginners alike.",
    faculty: "Student Affairs Division",
    members: 580,
    founded: "1998",
    coverUrl: "https://images.unsplash.com/photo-1575361204480-aadea25e6e68?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#1A7A4A",
  },
  "UTM Cultural & Heritage Society": {
    desc: "Celebrating the rich tapestry of Malaysian culture through traditional arts, performances, and heritage conservation activities. We preserve and promote the diverse traditions of our multi-ethnic campus community.",
    faculty: "Faculty of Built Environment",
    members: 190,
    founded: "2001",
    coverUrl: "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#B45309",
  },
  "UTM Entrepreneurship Club": {
    desc: "Nurturing the next generation of Malaysian entrepreneurs with pitching competitions, mentorship from industry founders, and startup incubation support. We connect student ideas with real funding opportunities.",
    faculty: "Azman Hashim International Business School",
    members: 310,
    founded: "2009",
    coverUrl: "https://images.unsplash.com/photo-1515187029135-18ee286d815b?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#520000",
  },
  "UTM Data Science Society": {
    desc: "Empowering students with practical data skills through workshops, Kaggle competitions, and industry-led hackathons. We bridge the gap between academic theory and real-world data science practice.",
    faculty: "Faculty of Computing",
    members: 175,
    founded: "2016",
    coverUrl: "https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#1A5CA8",
  },
  "UTM Muslim Students Society": {
    desc: "Strengthening faith, brotherhood, and social responsibility through religious education, community service, and interfaith dialogue. Open to all UTM students regardless of background.",
    faculty: "All Faculties",
    members: 820,
    founded: "1995",
    coverUrl: "https://images.unsplash.com/photo-1564769662533-4f00a87b4056?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#065F46",
  },
  "UTM Performing Arts Society": {
    desc: "Bringing the performing arts to campus life through dance, theatre, music, and collaborative creative productions. We stage multiple annual showcases and welcome performers of all experience levels.",
    faculty: "Faculty of Built Environment",
    members: 130,
    founded: "2003",
    coverUrl: "https://images.unsplash.com/photo-1518834107812-67b0b7c58434?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#6B21A8",
  },
  "UTM Career Services": {
    desc: "Bridging students with industry through career fairs, employer talks, CV clinics, and internship matching. We partner with 60+ companies annually to maximise graduate employment outcomes.",
    faculty: "Student Affairs Division",
    members: 400,
    founded: "2000",
    coverUrl: "https://images.unsplash.com/photo-1606857521015-7f9fcf423740?w=1200&h=400&fit=crop&auto=format",
    logoColor: "#1A1A1A",
  },
};

export const useSocietiesStore = defineStore("societies", {
  state: () => ({
    societies: { ...SOCIETIES },
  }),

  getters: {
    societyNames: (state) => Object.keys(state.societies),
    getSociety: (state) => (name) => state.societies[name],
  },
});
